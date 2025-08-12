#!/usr/bin/env node
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
import { parse } from 'csv-parse';
import pLimit from 'p-limit';
import { setTimeout as wait } from 'timers/promises';
import crypto from 'crypto';

const DEFAULT_CONCURRENCY = 8;
const DEFAULT_DEPTH = 3;             // how many levels under the first-level (level 2..depth)
const DEFAULT_BRANCHING = 1;         // how many children per node at each level
const DEFAULT_RETRIES = 4;
const DEFAULT_BACKOFF_MS = 500;      // initial backoff

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

function retryable(fn, { retries = DEFAULT_RETRIES, backoffMs = DEFAULT_BACKOFF_MS } = {}) {
  return async function wrapped(...args) {
    let attempt = 0;
    while (true) {
      try {
        return await fn(...args);
      } catch (err) {
        attempt++;
        if (attempt > retries) throw err;
        const delay = backoffMs * Math.pow(2, attempt - 1) + Math.floor(Math.random() * 100);
        console.warn(`Attempt ${attempt} failed. Retrying after ${delay}ms — error: ${err.message || err}`);
        await sleep(delay);
      }
    }
  };
}

// ------------------------------
// API functions
// ------------------------------
/**
 * Obtain token via client credentials (optional)
 * Returns access_token string
 */
async function fetchTokenWithClientCredentials({ tokenUrl, clientId, clientSecret }) {
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
async function createCategoryApi({ apiBaseUrl, token, payload }) {
  const url = new URL('/api/category', apiBaseUrl).toString();

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  });

  const text = await res.text();
  let json;
  try { json = text ? JSON.parse(text) : null; } catch (e) { json = { raw: text }; }

  if (!res.ok) {
    const message = json && json.message ? json.message : text;
    throw new Error(`API createCategory failed ${res.status} ${res.statusText}: ${message}`);
  }
  return json;
}

// Wrap createCategoryApi with retry/backoff for reuse
const createCategoryApiWithRetry = retryable(createCategoryApi, { retries: DEFAULT_RETRIES, backoffMs: DEFAULT_BACKOFF_MS });

// ------------------------------
// CSV helpers
// ------------------------------
function csvHeaders() {
  // match the headers you provided
  return ['id','parent_id','active','type','visible','name','external_link','description','meta_title','meta_description'];
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
}) {
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
          // Build a readable name: "ParentName > L{lvl+1}-{b}-{timestamp}"
          const newName = `${node.parentName} > L${lvl+1}-${b+1}-${timestamp}`;
          const payload = {
            id: newId,
            parentId: node.parentId,      // use parentId from node (initially the sub CSV id)
            name: newName,
            type: 'page',
            active: true,
            visible: true,
            description: `Auto-generated child of ${node.parentName} (level ${lvl+1})`,
            metaTitle: `Meta ${newName}`,
            metaDescription: `Meta desc for ${newName}`,
            // externalLink intentionally omitted or could be added
          };

          // Obtain token (getToken may be async)
          const token = await getToken();

          // create via API with retry/backoff
          const createWithRetry = createFnWithRetry({ apiBaseUrl, token });
          const res = await createWithRetry({ payload });

          // Shopware response might return the created id in different shapes, but since we
          // provided id, assume the id is the one we sent; otherwise we try to extract it
          const createdId = (res && res.data && res.data.id) ? res.data.id : newId;

          // Write to outStream row
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
          };
          outStream.write(rowToCsvLine(outRow, delimiter));

          // add to nextLevelNodes so we can create children of this node on the next iteration
          nextLevelNodes.push({ parentId: createdId, parentName: newName, level: lvl + 1 });
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
}

// ------------------------------
// CLI and execution
// ------------------------------
async function main() {
  // parse args (simple)
  const argv = process.argv.slice(2);
  const opts = {};
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      const key = a.slice(2);
      const next = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
      opts[key] = next;
    }
  }

  const subCsvPath = opts['sub-csv'] || 'sub_categories.csv';
  const outCsvPath = opts['out-csv'] || `deeper_created_${Date.now()}.csv`;
  const apiBaseUrl = opts['api-base'] || 'http://localhost:8000';
  const bearerToken = opts['token'] || process.env.SHOPWARE_TOKEN || null;
  const clientId = opts['client-id'] || process.env.SHOPWARE_CLIENT_ID || null;
  const clientSecret = opts['client-secret'] || process.env.SHOPWARE_CLIENT_SECRET || null;
  const tokenUrl = opts['token-url'] || (apiBaseUrl.replace(/\/$/, '') + '/api/oauth/token');

  const concurrency = parseInt(opts['concurrency'] || DEFAULT_CONCURRENCY, 10);
  const depth = parseInt(opts['depth'] || DEFAULT_DEPTH, 10);
  const branching = parseInt(opts['branching'] || DEFAULT_BRANCHING, 10);
  const delimiter = opts['delimiter'] || ',';
  const noHeader = !!opts['no-header'];

  // Input validation
  if (concurrency < 1) {
    console.error('Concurrency must be at least 1');
    process.exit(2);
  }
  if (depth < 1) {
    console.error('Depth must be at least 1');
    process.exit(2);
  }
  if (branching < 1) {
    console.error('Branching must be at least 1');
    process.exit(2);
  }

  if (!fs.existsSync(subCsvPath)) {
    console.error(`Input sub CSV not found: ${subCsvPath}`);
    process.exit(2);
  }

  // Token provider function
  let cachedToken = bearerToken;
  async function getToken() {
    if (cachedToken) return cachedToken;
    if (clientId && clientSecret) {
      cachedToken = await fetchTokenWithClientCredentials({ tokenUrl, clientId, clientSecret });
      return cachedToken;
    }
    throw new Error('No token provided and no client credentials available. Provide --token or --client-id & --client-secret');
  }

  // prepare output CSV stream
  const outStream = fs.createWriteStream(outCsvPath, { encoding: 'utf-8' });
  if (!noHeader) outStream.write(csvHeaders().join(delimiter) + '\n');

  // streaming CSV parse
  const parser = fs.createReadStream(subCsvPath).pipe(parse({
    columns: true,
    skip_empty_lines: true,
    trim: true,
  }));

  // concurrency limit for parents processing (we will process each first-level row in parallel up to concurrency)
  const limitParents = pLimit(concurrency);

  // For each parsed row
  const tasks = [];
  let processedCount = 0;
  let totalRecords = 0;
  
  // First pass: count total records for progress reporting
  const records = [];
  for await (const record of parser) {
    const parentId = (record.id || '').trim();
    const parentName = (record.name || '').trim();
    if (!parentId) {
      console.warn('Skipping record with no id:', record);
      continue;
    }
    if (!parentName) {
      console.warn('Skipping record with no name:', record);
      continue;
    }
    records.push({ id: parentId, name: parentName });
  }
  
  totalRecords = records.length;
  console.log(`Processing ${totalRecords} parent categories...`);
  
  // Process records
  for (const parentRecord of records) {

    // queue processing with concurrency
    tasks.push(limitParents(async () => {
      // Create the node(s)
      await processParentRecord({
        parentRecord,
        apiBaseUrl,
        getToken,
        depth,
        branching,
        concurrency: Math.max(1, Math.floor(concurrency / 2)), // internal concurrency per chain
        outStream,
        delimiter,
        createFnWithRetry: async ({ apiBaseUrl: optApiBaseUrl, token }) => {
          const effectiveToken = token || await getToken();
          const effectiveApiBaseUrl = optApiBaseUrl || apiBaseUrl;
          // Use the pre-built retry wrapper
          return async function createWithPayload({ payload }) {
            return createCategoryApiWithRetry({ 
              apiBaseUrl: effectiveApiBaseUrl, 
              token: effectiveToken, 
              payload 
            });
          };
        },
      });
      
      processedCount++;
      if (processedCount % 10 === 0 || processedCount === totalRecords) {
        console.log(`Progress: ${processedCount}/${totalRecords} parent categories processed`);
      }
    }));
  } // end records loop

  // wait for all parent tasks
  await Promise.all(tasks);

  outStream.end();
  console.log(`\nCompleted. Output CSV: ${outCsvPath}`);
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

/*
USAGE EXAMPLES:

1. Basic usage with existing CSV:
   node create_deep_categories_from_subcsv.js --sub-csv=my_categories.csv --token=your_token

2. Create 4 levels deep with 2 children per node:
   node create_deep_categories_from_subcsv.js --depth=4 --branching=2 --token=your_token

3. Use OAuth client credentials:
   node create_deep_categories_from_subcsv.js --client-id=your_id --client-secret=your_secret

4. Custom API endpoint and output file:
   node create_deep_categories_from_subcsv.js --api-base=https://myshop.com --out-csv=my_output.csv

5. Lower concurrency for rate-limited APIs:
   node create_deep_categories_from_subcsv.js --concurrency=2 --token=your_token

Environment variables can also be used:
   export SHOPWARE_TOKEN=your_token
   export SHOPWARE_CLIENT_ID=your_id
   export SHOPWARE_CLIENT_SECRET=your_secret
   node create_deep_categories_from_subcsv.js
*/
