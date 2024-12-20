import jsdoc2md from 'jsdoc-to-markdown';
import Handlebars from 'handlebars';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'url';
import util from 'util';

const __filename = fileURLToPath(import.meta.url); // get the resolved path to the file
const __dirname = path.dirname(__filename);

const sources = [
    'src/helper',
    // 'src/plugin',
    // 'src/service',
    // 'src/utility',
];

class DocsGenerator {

    static defaultOptions = {
        outputDir: 'docs',
    };

    constructor(options) {
        this.options = Object.assign({}, DocsGenerator.defaultOptions, options);

        const moduleTemplate = fs.readFileSync(path.resolve(`${__dirname}/templates/`, 'module.hbs'), 'utf8');

        this.template = Handlebars.compile(moduleTemplate);
    }

    generateDocs(sourceDir, cleanUp = true) {
        this.initOutputDir(cleanUp);

        if (sourceDir instanceof Array) {
            sourceDir.forEach((dir) => {
                this.processDir(dir, true);
            });
            return;
        } else if (typeof sourceDir === 'string') {
            this.processDir(sourceDir, true);
        }
    };

    initOutputDir(cleanUp = true) {
        const docsDir = this.options.outputDir;

        // Delete old content.
        if (fs.existsSync(docsDir) && cleanUp === true) {
            fs.rmSync(docsDir, { recursive: true, force: true });
        }

        // Create a fresh docs directory.
        try {
            fs.mkdirSync(docsDir);
        } catch (e) {
            console.error(e);
        }
    }

    processDir(dirPath, recursive = false, recursionDepth = 10, currentIteration = 1) {
        if (this.isFile(dirPath)) {
            this.generateDocsFile(dirPath);
            return;
        }

        const dirContent = fs.readdirSync(dirPath);

        this.createDocsDir(dirPath);

        dirContent.forEach((dirEntry) => {
            const dirEntryPath = path.join(dirPath, dirEntry);

            if (this.isFile(dirEntryPath)) {
                this.generateDocsFile(dirEntryPath);
            } else if (this.isDirectory(dirEntryPath) && recursive === true && currentIteration <= recursionDepth) {
                this.processDir(dirEntryPath, recursive, recursionDepth, currentIteration += 1);
            }
        });
    }

    createDocsDir(dirPath) {
        const docsPath = path.join(this.options.outputDir, dirPath);

        this.createDir(docsPath);
    }

    createDir(dirPath, recursive = true) {
        if (!fs.existsSync(dirPath)) {
            try {
                fs.mkdirSync(dirPath, { recursive: recursive });
            } catch (e) {
                console.error(e);
            }
        }
    }

    async generateDocsFile(filePath) {
        if (!filePath.includes('.js')) {
            return;
        }

        try {
            await jsdoc2md.clear();
        } catch (e) {
            // Do nothing.
        }

        const outputFilePath = path.join(this.options.outputDir, filePath.replace('.js', '.md'));
        const outputDirPath = path.dirname(outputFilePath);

        if (!fs.existsSync(outputDirPath)) {
            this.createDir(outputDirPath);
        }

        let output = '';
        let rawData = null;

        try {
            rawData = await jsdoc2md.getTemplateData({
                files: filePath,
                'heading-depth': 1,
                'no-cache': true,
                'no-gfm': true,
            });
        } catch (e) {
            console.warn('Could not parse file: ', filePath);
            console.error(e);
        }

        if (!rawData || !rawData.length) {
            return;
        }

        // Just for debugging. ToDo: Remove!
        if (filePath.includes('form-validation.helper')) {
            console.log('Template Data');
            console.log(util.inspect(rawData, {showHidden: false, depth: null, colors: true}));
        }

        output = this.createModuleTemplate(rawData);

        if (output && output.length) {
            fs.writeFileSync(outputFilePath, output, { flag: 'a' });
        }
    }

    createModuleTemplate(rawData) {
        let output = '';

        if (!rawData || !rawData.length) {
            return output;
        }

        const moduleData = rawData[0];

        if (!moduleData.kind === 'module' ||
            !moduleData.customTags ||
            !moduleData.customTags.find(t => t.tag === 'sw-docs')) {
            return output;
        }

        // eslint-disable-next-line
        console.log('Creating docs for module: ', moduleData.name);

        moduleData.methods = rawData.slice(1).filter((method) => {
            // Don't show private methods in public docs.
            return method.kind === 'function' &&
                !method.name.startsWith('_') &&
                method.access !== 'private';
        });

        try {
            output = this.template(moduleData);
        } catch (e) {
            console.warn('Failed to render template for module: ', moduleData.name);
            console.error(e);
        }

        return output;
    }

    isFile(filePath) {
        return fs.lstatSync(filePath).isFile();
    }

    isDirectory(dirPath) {
        return fs.lstatSync(dirPath).isDirectory();
    }
}

const generator = new DocsGenerator();
generator.generateDocs(sources);
