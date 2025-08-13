/**
 * create_deep_categories_from_subcsv.js
 *
 * Reads an existing sub-categories CSV (first-level categories), and for each row
 * creates deeper categories (level 2..depth) via Shopware Admin API.
 *
 * Output: a CSV with created categories (same schema as your CSVs).
 *
 * Usage examples are at the bottom of this file (see "USAGE" section).
 */

import fs from 'fs';
import path from 'path';
import { parse } from 'csv-parse';
import pLimit from 'p-limit';
import { setTimeout as wait } from 'timers/promises';
import crypto from 'crypto';
import dotenv from 'dotenv';

// Load environment variables from the .env file
dotenv.config({ path: '/Users/vndanlap-0154/sw67_dev/tests/acceptance/.env' });

// Helper function to clean up environment variables (remove quotes and semicolons)
function cleanEnvVar(value) {
  if (!value) return value;
  return value.toString().replace(/^['"]|['"];?$/g, '').trim();
}

const DEFAULT_CONCURRENCY = 2;          // how many concurrent API calls to make
const DEFAULT_DEPTH = 3;             // how many levels under the first-level (level 2..depth)
const DEFAULT_BRANCHING = 1;         // how many children per node at each level
const DEFAULT_RETRIES = 5;             // how many retries for API calls
const DEFAULT_BACKOFF_MS = 1000;      // initial backoff
const DEFAULT_API_DELAY_MS = 1000;     // delay between API calls to reduce server load

// Logging levels
const LOG_LEVELS = {
  ERROR: 0,
  WARN: 1,
  INFO: 2,
  DEBUG: 3
};

// Default retry strategies per endpoint
const DEFAULT_RETRY_STRATEGIES = {
  '/api/oauth/token': { retries: 3, backoffMs: 500, maxDelay: 5000 },
  '/api/category': { retries: 5, backoffMs: 1000, maxDelay: 15000 }
};

// Performance metrics
class Metrics {
  constructor() {
    this.startTime = Date.now();
    this.apiCalls = { total: 0, successful: 0, failed: 0, retried: 0 };
    this.categoriesCreated = 0;
    this.parentRecordsProcessed = 0;
    this.errors = [];
    this.retryStats = {};
  }

  recordApiCall(endpoint, success, retryCount = 0) {
    this.apiCalls.total++;
    if (success) {
      this.apiCalls.successful++;
      if (endpoint === '/api/category') this.categoriesCreated++;
    } else {
      this.apiCalls.failed++;
    }
    if (retryCount > 0) {
      this.apiCalls.retried++;
      this.retryStats[endpoint] = (this.retryStats[endpoint] || 0) + retryCount;
    }
  }

  recordError(error, context) {
    this.errors.push({ error: error.message, context, timestamp: new Date().toISOString() });
  }

  recordParentProcessed() {
    this.parentRecordsProcessed++;
  }

  getStats() {
    const duration = Date.now() - this.startTime;
    return {
      duration: `${(duration / 1000).toFixed(2)}s`,
      apiCalls: this.apiCalls,
      categoriesCreated: this.categoriesCreated,
      parentRecordsProcessed: this.parentRecordsProcessed,
      errorCount: this.errors.length,
      retryStats: this.retryStats,
      avgApiCallsPerSecond: (this.apiCalls.total / (duration / 1000)).toFixed(2)
    };
  }
}

// Structured logger
class Logger {
  constructor(level = LOG_LEVELS.INFO, enableFile = false, logFilePath = null) {
    this.level = level;
    this.enableFile = enableFile;
    this.logFilePath = logFilePath;
    this.logStream = enableFile && logFilePath ? fs.createWriteStream(logFilePath, { flags: 'a' }) : null;
  }

  _log(level, message, data = {}) {
    if (level > this.level) return;

    const timestamp = new Date().toISOString();
    const levelName = Object.keys(LOG_LEVELS).find(key => LOG_LEVELS[key] === level);
    const logEntry = {
      timestamp,
      level: levelName,
      message,
      ...data
    };

    const logLine = JSON.stringify(logEntry);
    
    // Console output with colors
    const colors = { ERROR: '\x1b[31m', WARN: '\x1b[33m', INFO: '\x1b[32m', DEBUG: '\x1b[36m' };
    const reset = '\x1b[0m';
    const dataStr = Object.keys(data).length > 0 ? ` ${JSON.stringify(data)}` : '';
    console.log(`${colors[levelName] || ''}[${levelName}] ${timestamp} - ${message}${dataStr}${reset}`);
    
    // File output
    if (this.logStream) {
      this.logStream.write(logLine + '\n');
    }
  }

  error(message, data) { this._log(LOG_LEVELS.ERROR, message, data); }
  warn(message, data) { this._log(LOG_LEVELS.WARN, message, data); }
  info(message, data) { this._log(LOG_LEVELS.INFO, message, data); }
  debug(message, data) { this._log(LOG_LEVELS.DEBUG, message, data); }

  close() {
    if (this.logStream) {
      this.logStream.end();
    }
  }
}

// Resume state management
class ResumeState {
  constructor(stateFilePath) {
    this.stateFilePath = stateFilePath;
    this.state = this.loadState();
  }

  loadState() {
    try {
      if (fs.existsSync(this.stateFilePath)) {
        const data = fs.readFileSync(this.stateFilePath, 'utf-8');
        const parsed = JSON.parse(data);
        // Convert array back to Set if it exists
        if (parsed.processedRecords && Array.isArray(parsed.processedRecords)) {
          parsed.processedRecords = new Set(parsed.processedRecords);
        } else {
          parsed.processedRecords = new Set();
        }
        return parsed;
      }
    } catch (err) {
      // Ignore errors, start fresh
    }
    return { processedRecords: new Set(), lastProcessedIndex: -1, createdCategories: [] };
  }

  saveState() {
    try {
      const stateToSave = {
        ...this.state,
        processedRecords: Array.from(this.state.processedRecords)
      };
      fs.writeFileSync(this.stateFilePath, JSON.stringify(stateToSave, null, 2));
    } catch (err) {
      console.warn('Failed to save resume state:', err.message);
    }
  }

  isProcessed(recordId) {
    return this.state.processedRecords.has(recordId);
  }

  markProcessed(recordId, createdCategories = []) {
    this.state.processedRecords.add(recordId);
    this.state.createdCategories.push(...createdCategories);
    this.saveState();
  }

  cleanup() {
    try {
      if (fs.existsSync(this.stateFilePath)) {
        fs.unlinkSync(this.stateFilePath);
      }
    } catch (err) {
      // Ignore cleanup errors
    }
  }
}

// ------------------------------
// Helper utilities
// ------------------------------
function uuidHex() {
  // Create 32-lowerhex UUID (Shopware expects ^[0-9a-f]{32}$)
  return crypto.randomUUID().replace(/-/g, '').toLowerCase();
}

function sleep(ms) {
  return wait(ms);
}

function retryable(fn, options = {}) {
  const { 
    retries = DEFAULT_RETRIES, 
    backoffMs = DEFAULT_BACKOFF_MS, 
    maxDelay = 30000,
    logger = null,
    metrics = null,
    endpoint = 'unknown'
  } = options;

  return async function wrapped(...args) {
    let attempt = 0;
    let lastError;
    
    while (attempt <= retries) {
      try {
        const result = await fn(...args);
        if (metrics) metrics.recordApiCall(endpoint, true, attempt);
        if (attempt > 0 && logger) {
          logger.info(`API call succeeded after ${attempt} retries`, { endpoint, attempt });
        }
        return result;
      } catch (err) {
        lastError = err;
        attempt++;
        
        if (attempt > retries) {
          if (metrics) metrics.recordApiCall(endpoint, false, attempt - 1);
          if (logger) logger.error(`API call failed after ${retries} retries`, { endpoint, error: err.message });
          throw err;
        }
        
        // Use longer delays for server errors (500, 502, 503, 504)
        const isServerError = err.message.includes('500') || err.message.includes('502') || 
                             err.message.includes('503') || err.message.includes('504');
        const serverErrorMultiplier = isServerError ? 3 : 1;
        
        const delay = Math.min(maxDelay, (backoffMs * serverErrorMultiplier) * Math.pow(2, attempt - 1) + Math.floor(Math.random() * 100));
        if (logger) {
          logger.warn(`API call failed, retrying in ${delay}ms`, { 
            endpoint, 
            attempt, 
            error: err.message,
            remainingRetries: retries - attempt + 1,
            isServerError,
            delayMultiplier: serverErrorMultiplier
          });
        }
        await sleep(delay);
      }
    }
    
    throw lastError;
  };
}

// ------------------------------
// API functions
// ------------------------------
/**
 * Obtain token via client credentials (optional)
 * Returns access_token string
 */
async function fetchTokenWithClientCredentials({ tokenUrl, clientId, clientSecret, dryRun = false, logger = null }) {
  if (dryRun) {
    if (logger) logger.info('[DRY RUN] Simulating token fetch', { tokenUrl });
    return 'dry-run-token-' + Date.now();
  }

  const body = new URLSearchParams({
    grant_type: 'client_credentials',
    client_id: clientId,
    client_secret: clientSecret,
    scope: 'write',
  });

  const res = await fetch(tokenUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  });

  if (!res.ok) {
    const txt = await res.text();
    const errorDetails = {
      url: tokenUrl,
      method: 'POST',
      status: res.status,
      statusText: res.statusText,
      response: txt,
      payload: {
        grant_type: 'client_credentials',
        client_id: clientId,
        client_secret: clientSecret ? '[REDACTED]' : undefined,
        scope: 'write'
      }
    };
    if (logger) {
      logger.error('Token request failed', errorDetails);
    }
    throw new Error(`Token request failed: ${res.status} ${res.statusText}: ${txt}`);
  }
  const json = await res.json();
  if (!json.access_token) throw new Error('Token response missing access_token');
  return json.access_token;
}

/**
 * Create a category via Admin API.
 * Body properties: id (32-hex), parentId (32-hex), name, description, metaTitle, metaDescription, externalLink, type, active, visible
 * Returns created resource JSON (Shopware returns detail or created entity)
 */
async function createCategoryApi({ apiBaseUrl, token, payload, dryRun = false, logger = null, getToken = null }) {
  if (dryRun) {
    if (logger) {
      logger.info('[DRY RUN] Simulating category creation', { 
        categoryId: payload.id, 
        categoryName: payload.name,
        parentId: payload.parentId 
      });
    }
    // Simulate API response structure
    return {
      data: {
        id: payload.id,
        attributes: {
          name: payload.name,
          parentId: payload.parentId
        }
      }
    };
  }

  const url = new URL('/api/category', apiBaseUrl).toString();

  // First attempt with current token
  let currentToken = token;
  let res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${currentToken}`,
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  });

  // If 401 error and we have a getToken function, refresh token and retry
  if (res.status === 401 && getToken) {
    if (logger) {
      logger.warn('Got 401 error, refreshing token and retrying', { 
        categoryId: payload.id,
        categoryName: payload.name 
      });
    }
    
    try {
      currentToken = await getToken(true); // Pass true to force refresh
      
      // Retry with new token
      res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${currentToken}`,
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });
      
      if (logger) {
        logger.info('Token refreshed and request retried', { 
          categoryId: payload.id,
          success: res.ok 
        });
      }
    } catch (tokenError) {
      if (logger) {
        logger.error('Failed to refresh token', { error: tokenError.message });
      }
      // Continue with original error handling below
    }
  }

  const text = await res.text();
  let json;
  try { json = text ? JSON.parse(text) : null; } catch (e) { json = { raw: text }; }

  if (!res.ok) {
    const message = json && json.message ? json.message : text;
    const errorDetails = {
      url,
      method: 'POST',
      status: res.status,
      statusText: res.statusText,
      message,
      response: text,
      payload: {
        ...payload,
        // Redact sensitive information if any
        id: payload.id,
        parentId: payload.parentId,
        name: payload.name
      },
      categoryId: payload.id,
      categoryName: payload.name
    };
    
    if (logger) {
      logger.error('Category creation API call failed', errorDetails);
    }
    
    // Provide more specific error messages for common issues
    if (res.status === 500) {
      throw new Error(`Server error (500) - possible overload. Try reducing concurrency. Details: ${JSON.stringify(errorDetails)}`);
    } else if (res.status === 429) {
      throw new Error(`Rate limit exceeded (429). Reduce concurrency or add delays. Details: ${JSON.stringify(errorDetails)}`);
    } else {
      throw new Error(`API createCategory failed ${res.status} ${res.statusText}: ${message}`);
    }
  }
  return json;
}

// Note: createCategoryApiWithRetry is now created dynamically in main() with custom retry strategies

// ------------------------------
// CSV helpers
// ------------------------------
function csvHeaders() {
  // match the headers you provided + add mid category info
  return ['id','parent_id','active','type','visible','name','external_link','description','meta_title','meta_description','mid_category_id','mid_category_name'];
}

/**
 * Load and index mid-categories for reference
 */
async function loadMidCategories(midCsvPath) {
  const midCategories = new Map();
  
  if (!fs.existsSync(midCsvPath)) {
    console.warn(`Mid categories CSV not found: ${midCsvPath}`);
    return midCategories;
  }

  return new Promise((resolve, reject) => {
    const parser = fs.createReadStream(midCsvPath).pipe(parse({
      columns: true,
      skip_empty_lines: true,
      trim: true,
    }));

    parser.on('data', (record) => {
      const midId = (record.id || '').trim();
      const midName = (record.name || '').trim();
      if (midId && midName) {
        midCategories.set(midId, {
          id: midId,
          name: midName,
          parent_id: record.parent_id,
          type: record.type,
          active: record.active,
          visible: record.visible
        });
      }
    });

    parser.on('error', reject);
    parser.on('end', () => {
      console.log(`Loaded ${midCategories.size} mid-categories from ${midCsvPath}`);
      resolve(midCategories);
    });
  });
}

function rowToCsvLine(row, delimiter = ',') {
  // properly quote and escape
  return csvHeaders().map(col => {
    const v = row[col] ?? '';
    const s = String(v);
    return `"${s.replace(/"/g, '""')}"`;
  }).join(delimiter) + '\n';
}

// ------------------------------
// Core logic
// ------------------------------
/**
 * For each first-level sub record, create deeper levels.
 *
 * Behavior: We create nodes level-by-level for a chain or branching:
 * - depth = number of levels to create under the first-level (e.g., depth=3 creates level2, level3, level4)
 * - branching = number of children per node at each level
 *
 * Example: depth=3, branching=2:
 *  For parent (first-level P):
 *   - create P-1a, P-1b (level2)
 *   - for each created: create two children at level3, ...
 *
 * parentRecord: object parsed from sub CSV (has id, name, etc.)
 */
async function processParentRecord({
  parentRecord, // parsed object from CSV (first-level sub)
  apiBaseUrl,
  getToken,     // function that returns token string (may call OAuth)
  depth,
  branching,
  concurrency,
  outStream,
  delimiter,
  createFnWithRetry,
  dryRun = false,
  logger = null,
  metrics = null,
  resumeState = null,
  midCategories = null // Add mid-categories map for reference
}) {
  // Check if already processed (resume capability)
  if (resumeState && resumeState.isProcessed(parentRecord.id)) {
    if (logger) {
      logger.info('Skipping already processed parent record', { parentId: parentRecord.id, parentName: parentRecord.name });
    }
    return;
  }

  const createdCategories = [];
  
  try {
    // We'll create progressive levels. We maintain a queue of nodes at current level (start with parentRecord)
    // But since parentRecord is the existing first-level, we start by creating children of parentRecord.id
    const timestamp = new Date().toISOString().replace(/[:.]/g,'-');

    // queue holds objects: { parentId, parentName, level }
    let currentLevelNodes = [{ parentId: parentRecord.id, parentName: parentRecord.name, level: 1 }];

    for (let lvl = 1; lvl <= depth; lvl++) {
      // lvl indicates: we will create nodes at level = lvl + 1 relative to original first-level
      const nextLevelNodes = [];

      // For each node in currentLevelNodes we will create `branching` children
      // Use concurrency via p-limit
      const limit = pLimit(concurrency);

      const tasks = [];
      for (const node of currentLevelNodes) {
        for (let b = 0; b < branching; b++) {
          tasks.push(limit(async () => {
            const newId = uuidHex();
            // Build a shorter readable name to avoid 255 char limit: "L{lvl+1}-{b}-{short_timestamp}"
            const shortTimestamp = timestamp.slice(-8); // Use last 8 chars of timestamp
            const newName = `L${lvl}-${b+1}-${shortTimestamp}`;
            const payload = {
              id: newId,
              parentId: node.parentId,      // use parentId from node (initially the sub CSV id)
              name: newName,
              type: 'page',
              active: true,
              visible: true,
              description: `Auto-generated child of "${node.parentName}" (level ${lvl})`,
              metaTitle: `Meta ${newName}`,
              metaDescription: `Meta desc for ${newName} under ${node.parentName}`,
              // externalLink intentionally omitted or could be added
            };

            if (logger) {
              logger.debug('Creating category', { 
                categoryId: newId, 
                categoryName: newName, 
                parentId: node.parentId,
                level: lvl + 1 
              });
            }

            // Obtain token (getToken may be async) - skip in dry-run mode
            const token = dryRun ? 'dry-run-token' : await getToken();

            // create via API with retry/backoff - use the simple retry function directly
            const res = await createFnWithRetry({ apiBaseUrl, token, payload, dryRun, logger, getToken });

            // Add delay after API call to reduce server load (skip in dry-run mode)
            if (!dryRun) {
              await sleep(DEFAULT_API_DELAY_MS);
            }

            // Shopware response might return the created id in different shapes, but since we
            // provided id, assume the id is the one we sent; otherwise we try to extract it
            const createdId = (res && res.data && res.data.id) ? res.data.id : newId;

            // Track created category for resume state
            createdCategories.push({ id: createdId, parentId: node.parentId, name: newName, level: lvl + 1 });

            // Write to outStream row (skip in dry-run mode for cleaner output)
            if (!dryRun) {
              // Find mid-category information based on parentRecord's parent_id
              let midCategoryId = '';
              let midCategoryName = '';
              if (midCategories && parentRecord.parent_id) {
                const midCategory = midCategories.get(parentRecord.parent_id);
                if (midCategory) {
                  midCategoryId = midCategory.id;
                  midCategoryName = midCategory.name;
                }
              }

              const outRow = {
                id: createdId,
                parent_id: node.parentId,
                active: payload.active ? 1 : 0,
                type: payload.type,
                visible: payload.visible ? 1 : 0,
                name: newName,
                external_link: '', // left blank for generated nodes
                description: payload.description,
                meta_title: payload.metaTitle,
                meta_description: payload.metaDescription,
                mid_category_id: midCategoryId,
                mid_category_name: midCategoryName,
              };
              outStream.write(rowToCsvLine(outRow, delimiter));
            }

            // add to nextLevelNodes so we can create children of this node on the next iteration
            nextLevelNodes.push({ parentId: createdId, parentName: newName, level: lvl + 1 });

            if (logger) {
              logger.debug('Category created successfully', { 
                categoryId: createdId, 
                categoryName: newName,
                dryRun 
              });
            }
          }));
        } // end branching
      } // end currentLevelNodes loop

      // wait for all tasks of this level
      await Promise.all(tasks);

      // move to next level
      currentLevelNodes = nextLevelNodes;

      // If no nodes were created at this level (branching 0 or other), stop early
      if (currentLevelNodes.length === 0) break;
    } // end depth loop

    // Mark as processed in resume state
    if (resumeState) {
      resumeState.markProcessed(parentRecord.id, createdCategories);
    }

    if (metrics) {
      metrics.recordParentProcessed();
    }

    if (logger) {
      logger.info('Parent record processed successfully', { 
        parentId: parentRecord.id, 
        parentName: parentRecord.name,
        categoriesCreated: createdCategories.length,
        dryRun 
      });
    }

  } catch (error) {
    if (logger) {
      logger.error('Failed to process parent record', { 
        parentId: parentRecord.id, 
        parentName: parentRecord.name,
        error: error.message,
        stack: error.stack
      });
    }
    
    if (metrics) {
      metrics.recordError(error, { parentId: parentRecord.id, parentName: parentRecord.name });
    }
    
    throw error; // Re-throw to be handled by caller
  }
}

// ------------------------------
// CLI and execution
// ------------------------------
async function main() {
  // parse args (enhanced)
  const argv = process.argv.slice(2);
  const opts = {};
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      if (a.includes('=')) {
        // Handle --key=value format
        const [key, value] = a.slice(2).split('=', 2);
        opts[key] = value;
      } else {
        // Handle --key value or --flag format
        const key = a.slice(2);
        const next = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
        opts[key] = next;
      }
    }
  }

  // Debug: log parsed arguments
  console.log('DEBUG: Parsed arguments:', opts);
  console.log('DEBUG: Raw argv:', argv);

  // Enhanced options
  const subCsvPath = opts['sub-csv'] || 'sub_categories.csv';
  const outCsvPath = opts['out-csv'] || `deeper_created_${Date.now()}.csv`;
  const apiBaseUrl = opts['api-base'] || cleanEnvVar(process.env.APP_URL) || 'https://van-snapshot-test-1.swstage.store';
  const bearerToken = opts['token'] || cleanEnvVar(process.env.TOKEN) || process.env.SHOPWARE_TOKEN || null;
  const clientId = opts['client-id'] || cleanEnvVar(process.env.SHOPWARE_ACCESS_KEY_ID) || process.env.SHOPWARE_CLIENT_ID || null;
  const clientSecret = opts['client-secret'] || cleanEnvVar(process.env.SHOPWARE_SECRET_ACCESS_KEY) || process.env.SHOPWARE_CLIENT_SECRET || null;
  const tokenUrl = opts['token-url'] || (apiBaseUrl.replace(/\/$/, '') + '/api/oauth/token');

  const concurrency = parseInt(opts['concurrency'] || DEFAULT_CONCURRENCY, 10);
  const depth = parseInt(opts['depth'] || DEFAULT_DEPTH, 10);
  const branching = parseInt(opts['branching'] || DEFAULT_BRANCHING, 10);
  const delimiter = opts['delimiter'] || ',';
  const noHeader = !!opts['no-header'];

  // New enhanced options
  const dryRun = !!opts['dry-run'];
  const resume = !!opts['resume'];
  const logLevel = opts['log-level'] || 'INFO';
  const enableFileLogging = !!opts['enable-file-logging'];
  const logFilePath = opts['log-file'] || `category-creation-${Date.now()}.log`;
  const retryConfigPath = opts['retry-config'] || null;
  const metricsOutputPath = opts['metrics-output'] || null;
  const stateFilePath = opts['state-file'] || `category-creation-state-${path.basename(subCsvPath, '.csv')}.json`;

  // Debug: log environment variables and argument parsing
  console.log('DEBUG: Environment variables loaded:', {
    apiBaseUrl,
    hasToken: !!bearerToken,
    hasClientId: !!clientId,
    hasClientSecret: !!clientSecret,
    rawAppUrl: process.env.APP_URL,
    cleanedAppUrl: cleanEnvVar(process.env.APP_URL),
    depth,
    branching,
    concurrency
  });

  // Initialize logger
  const logLevelNum = LOG_LEVELS[logLevel.toUpperCase()] ?? LOG_LEVELS.INFO;
  const logger = new Logger(logLevelNum, enableFileLogging, logFilePath);

  // Test logger immediately
  try {
    logger.info('Logger initialized successfully', { logLevel, enableFileLogging, logFilePath });
  } catch (err) {
    console.error('Logger initialization failed:', err);
    process.exit(1);
  }

  // Initialize metrics
  const metrics = new Metrics();

  // Initialize resume state
  const resumeState = resume ? new ResumeState(stateFilePath) : null;

  logger.info('Starting category creation script', {
    subCsvPath,
    outCsvPath,
    apiBaseUrl,
    concurrency,
    depth,
    branching,
    dryRun,
    resume,
    logLevel,
    enableFileLogging
  });

  // Input validation
  if (concurrency < 1) {
    logger.error('Concurrency must be at least 1');
    process.exit(2);
  }
  if (depth < 1) {
    logger.error('Depth must be at least 1');
    process.exit(2);
  }
  if (branching < 1) {
    logger.error('Branching must be at least 1');
    process.exit(2);
  }

  if (!fs.existsSync(subCsvPath)) {
    logger.error(`Input sub CSV not found: ${subCsvPath}`);
    process.exit(2);
  }

  // Load custom retry strategies if provided
  let retryStrategies = DEFAULT_RETRY_STRATEGIES;
  if (retryConfigPath && fs.existsSync(retryConfigPath)) {
    try {
      const customRetries = JSON.parse(fs.readFileSync(retryConfigPath, 'utf-8'));
      retryStrategies = { ...DEFAULT_RETRY_STRATEGIES, ...customRetries };
      logger.info('Loaded custom retry strategies', { retryConfigPath });
    } catch (err) {
      logger.warn('Failed to load retry config, using defaults', { error: err.message });
    }
  }

  // Create retry wrappers with custom strategies
  const createCategoryApiWithRetry = retryable(createCategoryApi, {
    ...retryStrategies['/api/category'],
    logger,
    metrics,
    endpoint: '/api/category'
  });

  const fetchTokenWithRetry = retryable(fetchTokenWithClientCredentials, {
    ...retryStrategies['/api/oauth/token'],
    logger,
    metrics,
    endpoint: '/api/oauth/token'
  });

  // Token provider function
  let cachedToken = bearerToken;
  async function getToken(forceRefresh = false) {
    if (cachedToken && !forceRefresh) return cachedToken;
    if (clientId && clientSecret) {
      if (forceRefresh && logger) {
        logger.info('Forcing token refresh', { reason: 'received 401 error' });
      }
      cachedToken = await fetchTokenWithRetry({ 
        tokenUrl, 
        clientId, 
        clientSecret, 
        dryRun, 
        logger 
      });
      return cachedToken;
    }
    if (forceRefresh && !clientId) {
      throw new Error('Cannot refresh token: no client credentials available. Provide --client-id & --client-secret for automatic token refresh');
    }
    throw new Error('No token provided and no client credentials available. Provide --token or --client-id & --client-secret');
  }

  // prepare output CSV stream (skip in dry-run mode)
  let outStream = null;
  if (!dryRun) {
    outStream = fs.createWriteStream(outCsvPath, { encoding: 'utf-8' });
    if (!noHeader) outStream.write(csvHeaders().join(delimiter) + '\n');
  }

  // streaming CSV parse
  const parser = fs.createReadStream(subCsvPath).pipe(parse({
    columns: true,
    skip_empty_lines: true,
    trim: true,
  }));

  // concurrency limit for parents processing
  const limitParents = pLimit(concurrency);

  // For streaming processing with progress tracking
  let processedCount = 0;
  let errorCount = 0;
  
  if (dryRun) {
    logger.info('🧪 DRY RUN MODE - No actual API calls will be made');
  }
  
  if (resume && resumeState) {
    logger.info('📂 RESUME MODE - Skipping already processed records', {
      alreadyProcessed: resumeState.state.processedRecords.size
    });
  }

  logger.info(`Starting to process categories from ${subCsvPath}...`);

  // Load mid-categories for relationship mapping
  let midCategories = null;
  try {
    const midCsvPath = path.join(path.dirname(subCsvPath), 'mid_categories.csv');
    if (fs.existsSync(midCsvPath)) {
      midCategories = await loadMidCategories(midCsvPath);
      logger.info(`Loaded ${midCategories.size} mid-categories from ${midCsvPath}`);
    } else {
      logger.warn(`Mid-categories file not found: ${midCsvPath}. Relationship mapping will be unavailable.`);
    }
  } catch (error) {
    logger.warn('Failed to load mid-categories', { error: error.message });
  }

  // Process records as they come from the stream
  let recordCount = 0;
  for await (const record of parser) {
    recordCount++;
    
    // Log first record for debugging
    if (recordCount === 1) {
      logger.debug('First CSV record structure', { record, availableColumns: Object.keys(record) });
    }
    
    // Validate required fields: id, name
    const parentId = (record.id || '').trim();
    const parentName = (record.name || '').trim();
    if (!parentId) {
      logger.warn('Skipping record with no id', { recordNumber: recordCount, record });
      continue;
    }
    if (!parentName) {
      logger.warn('Skipping record with no name', { recordNumber: recordCount, record });
      continue;
    }

    // Process each parent record with concurrency control
    await limitParents(async () => {
      try {
        await processParentRecord({
          parentRecord: { id: parentId, name: parentName, parent_id: (record.parent_id || '').trim() },
          apiBaseUrl,
          getToken,
          depth,
          branching,
          concurrency: Math.max(1, Math.floor(concurrency / 4)), // internal concurrency per chain - very conservative
          outStream,
          delimiter,
          createFnWithRetry: createCategoryApiWithRetry,
          dryRun,
          logger,
          metrics,
          resumeState,
          midCategories
        });
        
        processedCount++;
        if (processedCount % 10 === 0) {
          const stats = metrics.getStats();
          logger.info(`Progress update`, { 
            processedCount, 
            categoriesCreated: stats.categoriesCreated,
            apiCalls: stats.apiCalls.total,
            errorCount: stats.errorCount,
            avgApiCallsPerSecond: stats.avgApiCallsPerSecond
          });
        }
      } catch (error) {
        errorCount++;
        logger.error('Failed to process parent record', {
          parentId,
          parentName,
          error: error.message,
          stack: error.stack
        });
        // Don't re-throw in dry-run mode to continue processing other records
        if (!dryRun) {
          throw error;
        }
      }
    });
  } // end streaming loop

  if (outStream) {
    outStream.end();
  }

  // Final metrics and summary
  const finalStats = metrics.getStats();
  
  logger.info('🎉 Processing completed!', {
    ...finalStats,
    processedParents: processedCount,
    errorCount,
    outputFile: dryRun ? 'N/A (dry-run)' : outCsvPath,
    dryRun
  });

  // Save metrics to file if requested
  if (metricsOutputPath) {
    try {
      const metricsReport = {
        summary: finalStats,
        processedParents: processedCount,
        errorCount,
        errors: metrics.errors,
        configuration: {
          subCsvPath,
          outCsvPath,
          concurrency,
          depth,
          branching,
          dryRun,
          resume
        },
        timestamp: new Date().toISOString()
      };
      fs.writeFileSync(metricsOutputPath, JSON.stringify(metricsReport, null, 2));
      logger.info('Metrics saved', { metricsOutputPath });
    } catch (err) {
      logger.warn('Failed to save metrics', { error: err.message });
    }
  }

  // Cleanup resume state on successful completion (unless errors occurred)
  if (resumeState && errorCount === 0) {
    resumeState.cleanup();
    logger.info('Resume state cleaned up (successful completion)');
  }

  // Close logger
  logger.close();

  // Exit with appropriate code
  process.exit(errorCount > 0 ? 1 : 0);
}

// If running directly
if (import.meta.url === `file://${process.argv[1]}`) {
  // Ensure fetch is available (Node 18+). If not, recommend node-fetch
  if (typeof fetch !== 'function') {
    console.error('Global fetch is not available. Use Node 18+, or install node-fetch and modify script to import it.');
    process.exit(1);
  }

  main().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
  });
}