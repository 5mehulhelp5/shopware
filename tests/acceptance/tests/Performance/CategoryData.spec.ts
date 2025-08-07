import { test, expect } from '@fixtures/AcceptanceTest';
import type { Category } from '@shopware-ag/acceptance-test-suite';

const createCategoriesInBatches = async (
    createFunction: (name: string, parentId?: string) => Promise<Category>,
    count: number,
    parentId?: string,
    timestamp?: string,
    isSubCategory = false,
) => {
    const batchSize = 2; // Reduced batch size to be gentler on the server
    const batchDelay = 2000; // Increased delay between batches to 2 seconds
    const categoryPromises = [];
    const createdCategories = [];

    console.log(`Creating ${count} ${isSubCategory ? 'subcategories' : 'root categories'} in batches of ${batchSize}...`);

    for (let i = 0; i < count; i++) {
        // Corrected naming logic to ensure only subcategories get the "SubCategory" prefix
        const categoryName = isSubCategory
            ? `SubCategory-${parentId}-${i}-${timestamp}`
            : `RootCategory-${i}-${timestamp}`;

        categoryPromises.push(createFunction(categoryName, parentId));

        if (categoryPromises.length === batchSize || i === count - 1) {
            console.log(`Processing batch ${Math.floor(i / batchSize) + 1}/${Math.ceil(count / batchSize)}...`);
            createdCategories.push(...await Promise.all(categoryPromises));
            categoryPromises.length = 0;
            
            // Only add delay if not the last batch
            if (i < count - 1) {
                await new Promise(resolve => setTimeout(resolve, batchDelay));
            }
        }
    }
    return createdCategories;
};

test('As a shop administrator, I can create 50 root categories and 100 subcategories for each, totaling 5000 categories, as children of the Home category, with unique meta information, and not clean up the data.',
    { tag: ['@Categories', '@PerformanceData'] }, async ({
    TestDataService,
}) => {
    let hrs = 60 * 1000; // 60 minutes in milliseconds
    test.setTimeout(hrs*4); // Set timeout to 240 minutes for this heavy performance test
    TestDataService.setCleanUp(false);

    const rootCategoryCount = 50;
    const subCategoryCount = 100;
    let homeCategoryId: string;
    let rootCategories: Category[] = [];

    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');

    await test.step('Find the Home category to use as a parent.', async () => {
        const response = await TestDataService.AdminApiClient.post('search/category', {
            data: {
                limit: 1,
                filter: [{
                    type: 'equals',
                    field: 'name',
                    value: 'Home',
                }],
            },
        });
        const result = await response.json();
        expect(result.data.length).toBe(1);
        homeCategoryId = result.data[0].id;
    });

    await test.step(`Create ${rootCategoryCount} root level categories under "Home" with meta data.`, async () => {
        rootCategories = await createCategoriesInBatches(
            (name, parentId) => TestDataService.createCategory({
                name,
                parentId,
                metaTitle: `Meta Title for ${name}`,
                metaDescription: `Meta Description for ${name}`,
                description: `Description for ${name}`,
            }),
            rootCategoryCount,
            homeCategoryId,
            timestamp,
            false, // isSubCategory = false for root categories
        );
        
        // Wait longer for root categories to be fully committed and server to recover
        console.log('Waiting for server to recover after root category creation...');
        await new Promise(resolve => setTimeout(resolve, 5000)); // Increased to 5 seconds
        
        // Verify all root categories are accessible before creating subcategories
        console.log(`Verifying ${rootCategories.length} root categories are accessible...`);
        for (const rootCat of rootCategories) {
            const verifyResponse = await TestDataService.AdminApiClient.get(`category/${rootCat.id}`);
            if (!verifyResponse.ok) {
                throw new Error(`Root category ${rootCat.id} not found, potential race condition detected`);
            }
        }
    });

    await test.step(`For each new root category, create ${subCategoryCount} subcategories with meta data.`, async () => {
        for (let i = 0; i < rootCategories.length; i++) {
            const rootCat = rootCategories[i];
            console.log(`Creating subcategories for root category ${i + 1}/${rootCategories.length}: ${rootCat.name}`);
            
            await createCategoriesInBatches(
                (name, parentId) => TestDataService.createCategory({
                    name,
                    parentId,
                    metaTitle: `Meta Title for ${name}`,
                    metaDescription: `Meta Description for ${name}`,
                    description: `Description for ${name}`,
                }),
                subCategoryCount,
                rootCat.id,
                timestamp,
                true, // isSubCategory = true for subcategories
            );
            
            // Add delay between processing each root category's subcategories
            if (i < rootCategories.length - 1) {
                console.log('Waiting before processing next root category...');
                await new Promise(resolve => setTimeout(resolve, 3000)); // 3 second delay between root categories
            }
        }
    });

    // Verification step for root and sub-categories
    await test.step('Verify the count of created categories by name.', async () => {
        // Verify root categories
        const rootSearchTerm = `RootCategory-`;
        const rootCategoriesResponse = await TestDataService.AdminApiClient.post('search/category', {
            data: {
                limit: rootCategoryCount,
                filter: [{
                    type: 'contains',
                    field: 'name',
                    value: rootSearchTerm,
                }],
            },
        });
        const rootResult = await rootCategoriesResponse.json();
        expect(rootResult.total).toBe(rootCategoryCount);
        console.log(`Root categories created: ${rootResult.total}`);

        // Verify subcategories
        const subSearchTerm = `SubCategory-`;
        const subCategoriesResponse = await TestDataService.AdminApiClient.post('search/category', {
            data: {
                limit: rootCategoryCount * subCategoryCount,
                filter: [{
                    type: 'contains',
                    field: 'name',
                    value: subSearchTerm,
                }],
            },
        });
        const subResult = await subCategoriesResponse.json();
        expect(subResult.total).toBe(rootCategoryCount * subCategoryCount);
        console.log(`Subcategories created: ${subResult.total}`);
    });
});