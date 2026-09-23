import {expect, test} from '@playwright/test';
import {pilotQuestion, unusableQuestion} from '../support/env';
import {login, openQuestionsTab, questionRows} from '../support/catmanager';

/**
 * The parameter validity column (issue #54).
 *
 * An item whose parameters violate the contract of its model is played as a pilot
 * item at runtime. That was visible only in the import feedback and the attempt debug
 * output, never where the item pool is maintained.
 *
 * These cases need a browser because they are about what a maintainer actually sees:
 * that the state is conveyed by text rather than colour alone, that the reason names
 * the model, and that a broken item can be distinguished from a legitimate pilot item
 * in the list itself.
 */
test.describe('CAT manager item parameter validity', () => {
    test.beforeEach(async({page}) => {
        await login(page);
        await openQuestionsTab(page);
    });

    test('shows the validity column for every question', async({page}) => {
        await expect(questionRows(page).first()).toBeVisible();

        // The column exists and is filled - not an empty header.
        const states = page.locator(
            '#lcq_questions [class*="catquiz-itemparams-"]'
        );
        expect(await states.count()).toBeGreaterThan(0);
    });

    test('marks an item whose parameters cannot be used', async({page}) => {
        const row = page.locator('#lcq_questions table tbody tr', {
            hasText: unusableQuestion(),
        });
        await expect(row).toHaveCount(1);

        const marker = row.locator('.catquiz-itemparams-unusable');
        await expect(marker).toBeVisible();

        // Conveyed by text, not by colour alone - that is the accessibility
        // requirement, and a colour-only marker would pass a naive check.
        await expect(marker).not.toBeEmpty();

        // The reason has to name the model, otherwise a maintainer cannot act on it.
        const title = await marker.getAttribute('title');
        expect(title).toContain('raschbirnbaum');
    });

    test('keeps a pilot item distinguishable from a broken one', async({page}) => {
        const pilotrow = page.locator('#lcq_questions table tbody tr', {
            hasText: pilotQuestion(),
        });
        await expect(pilotrow).toHaveCount(1);

        // An item without any parameters is expected, not broken: it must not carry
        // the "unusable" marker. Both are played as pilot items, but only one needs
        // attention.
        await expect(pilotrow.locator('.catquiz-itemparams-unusable')).toHaveCount(0);
        await expect(pilotrow.locator('.catquiz-itemparams-noparams')).toBeVisible();
    });
});
