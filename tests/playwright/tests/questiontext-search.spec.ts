import {expect, test} from '@playwright/test';
import {matchingQuestion, matchingTerm, missingTerm} from '../support/env';
import {login, noRecordsMessage, openQuestionsTab, questionRows, searchQuestionText} from '../support/catmanager';

/**
 * Searching inside question texts (issue #20).
 *
 * The question list deliberately no longer selects the question text - carrying it
 * in every row is what made the list slow. Searching it therefore runs as its own
 * step: a small dedicated query resolves the matching ids and the list is narrowed
 * to them.
 *
 * These are the cases that a unit test cannot cover, because they depend on the
 * restriction surviving the table's own AJAX reload: the table serialises its SQL
 * into a cached instance that later reloads are built from, so a restriction added
 * at render time would look right on the first page and silently disappear
 * afterwards.
 */
test.describe('CAT manager question text search', () => {
    test.beforeEach(async({page}) => {
        await login(page);
    });

    test('finds a question by a word that appears only in its text', async({page}) => {
        await openQuestionsTab(page);
        const before = await questionRows(page).count();
        expect(before).toBeGreaterThan(1);

        await searchQuestionText(page, matchingTerm());

        await expect(page.getByText(matchingQuestion())).toBeVisible();

        // The search has to narrow the list. Merely finding the question would also
        // succeed on a completely unfiltered list.
        await expect(questionRows(page)).toHaveCount(1);
    });

    test('keeps the search term and the selected scale after submitting', async({page}) => {
        await openQuestionsTab(page);

        await searchQuestionText(page, matchingTerm());

        // The form is a GET form; without carrying the page parameters over, the user
        // would silently end up looking at a different scale than before.
        await expect(page.locator('#catquiz-qtsearch')).toHaveValue(matchingTerm());
        await expect(page.getByText(matchingQuestion())).toBeVisible();
    });

    test('a term nobody uses empties the list instead of ignoring the search', async({page}) => {
        await openQuestionsTab(page);

        await searchQuestionText(page, missingTerm());

        // This is the case that caught the real defect: the restriction used to be
        // applied while rendering, which is after the table had already serialised
        // its SQL for the AJAX reloads. The first page looked filtered and every
        // reload showed all records again.
        await expect(page.getByText(matchingQuestion())).toHaveCount(0);
        // The table reports an empty result explicitly rather than rendering zero
        // rows, so that message is the reliable assertion here.
        await expect(noRecordsMessage(page)).toBeVisible();
    });
});
