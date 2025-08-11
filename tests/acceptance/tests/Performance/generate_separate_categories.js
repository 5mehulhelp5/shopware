// generate_separate_categories_dual.js
import fs from 'fs';
import { faker } from '@faker-js/faker';
import { argv } from 'process';

// === Config ===
const ENABLED_COLUMNS = {
    id: true,
    parent_id: true,
    active: true,
    type: true,
    visible: true,
    name: true,
    external_link: true,
    description: true,
    meta_title: true,
    meta_description: true,
    products: true
};

const TOP_PARENT_ID = '01983b11d12e71d1859626764f252977';
const PRODUCT_ID = '01983b16e4667063a3fb8ae4b2539715';

function maybe(prob, generator) {
    return Math.random() > prob ? generator() : '';
}

function generateSlugFromName(name) {
    return name.toLowerCase().replace(/\s+/g, '-');
}

function generateUrlFromSlug(slug) {
    return `https://shop.example.com/collections/${encodeURIComponent(slug)}`;
}

function buildRow(cat, nullProb) {
    const slug = generateSlugFromName(cat.name);
    const fullName = `${cat.prefix}: ${cat.name}`;

    const row = {
        id: cat.id,
        parent_id: cat.parent_id,
        active: faker.number.int({ min: 0, max: 1 }),
        type: faker.helpers.arrayElement(['page', 'folder', 'link']),
        visible: faker.number.int({ min: 0, max: 1 }),
        name: fullName,
        external_link: maybe(nullProb, () => faker.internet.url()),
        description: faker.lorem.paragraph(3),
        meta_title: maybe(nullProb, () => faker.lorem.sentence(6)),
        meta_description: maybe(nullProb, () => faker.lorem.text().substring(0, 100)),
        products: PRODUCT_ID
    };

    return Object.entries(ENABLED_COLUMNS)
        .filter(([_, enabled]) => enabled)
        .map(([col]) => row[col]);
}

function parseArgs() {
    const args = {};
    for (let i = 2; i < argv.length; i++) {
        if (argv[i].startsWith('--')) {
            const key = argv[i].replace(/^--/, '');
            const value = argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
            args[key] = value;
        }
    }
    return {
        midCount: parseInt(args['mid-count'] ?? '50', 10),
        subPerMid: parseInt(args['sub-per-mid'] ?? '100', 10),
        midOut: args['mid-out'] ?? `mid_categories_${Date.now()}.csv`,
        subOut: args['sub-out'] ?? `sub_categories_${Date.now()}.csv`,
        delimiter: args['delimiter'] ?? ',',
        nullProb: parseFloat(args['null-prob'] ?? '0'),
        noHeader: !!args['no-header']
    };
}

function writeDualCSVStream({ midCount, subPerMid, midOut, subOut, delimiter, nullProb, noHeader }) {
    const midStream = fs.createWriteStream(midOut, { encoding: 'utf-8' });
    const subStream = fs.createWriteStream(subOut, { encoding: 'utf-8' });
    const headers = Object.entries(ENABLED_COLUMNS)
        .filter(([_, enabled]) => enabled)
        .map(([col]) => col);

    if (!noHeader) {
        midStream.write(headers.join(delimiter) + '\n');
        subStream.write(headers.join(delimiter) + '\n');
    }

    let midCountWritten = 0;
    let subCountWritten = 0;

    for (let i = 0; i < midCount; i++) {
        // Mid category
        const midId = faker.string.uuid().replace(/-/g, '');
        const midName = faker.commerce.department();
        const midRow = buildRow({ id: midId, parent_id: TOP_PARENT_ID, prefix: 'Mid', name: midName }, nullProb);
        midStream.write(midRow.join(delimiter) + '\n');
        midCountWritten++;

        // Sub categories
        for (let j = 0; j < subPerMid; j++) {
            const subId = faker.string.uuid().replace(/-/g, '');
            const subName = faker.commerce.department();
            const subRow = buildRow({ id: subId, parent_id: midId, prefix: 'Sub', name: subName }, nullProb);
            subStream.write(subRow.join(delimiter) + '\n');
            subCountWritten++;
        }

        if (i % 10 === 0) {
            process.stdout.write(`\rMid: ${midCountWritten} | Sub: ${subCountWritten} rows written...`);
        }
    }

    midStream.end();
    subStream.end(() => {
        console.log(`\n✅ Done! ${midCountWritten} mid categories → ${midOut}`);
        console.log(`✅ Done! ${subCountWritten} sub categories → ${subOut}`);
    });
}

if (import.meta.url === `file://${process.argv[1]}`) {
    const args = parseArgs();
    writeDualCSVStream(args);
}
