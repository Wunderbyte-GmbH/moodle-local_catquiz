import {Page, expect} from '@playwright/test';
import {adminPass, adminUser, contextId, scaleId} from './env';

/**
 * Logs in through the normal login form.
 *
 * @param page The Playwright page.
 */
export const login = async(page: Page): Promise<void> => {
    await page.goto('/login/index.php', {waitUntil: 'domcontentloaded'});
    // Wait for the form explicitly instead of relying on the default action
    // timeout: on a cold Moodle the login page can take considerably longer than a
    // single action is normally allowed.
    await page.waitForSelector('#username', {timeout: 60_000});
    await page.fill('#username', adminUser());
    await page.fill('#password', adminPass());
    await page.click('#loginbtn');
    // The login form re-renders itself on failure, so waiting for it to disappear
    // is what actually proves the login worked.
    //
    // The generous timeout is for the very first request against a freshly started
    // site: Moodle builds its caches on demand, and that first login POST can take
    // far longer than a warm one. Without it the first test of a run failed while
    // every later one passed - which looks like flakiness but is a cold cache.
    await expect(page.locator('#loginbtn')).toHaveCount(0, {timeout: 60_000});
};

/**
 * Opens the CAT manager on the seeded scale and switches to the questions tab.
 *
 * @param page The Playwright page.
 * @param extraParams Additional query parameters, e.g. the search term.
 */
export const openQuestionsTab = async(
    page: Page,
    extraParams: Record<string, string> = {}
): Promise<void> => {
    const params = new URLSearchParams({
        scaleid: scaleId(),
        contextid: contextId(),
        ...extraParams,
    });

    await page.goto(`/local/catquiz/manage_catscales.php?${params.toString()}`);

    // The questions table lives in a Bootstrap tab pane that starts hidden. Clicking
    // the tab is what makes it visible; asserting on the pane before that would pass
    // against markup nobody can see.
    await page.click('#questions-tab');
    await expect(page.locator('#lcq_questions')).toBeVisible();

    // The table renders its rows through an AJAX reload, so waiting for the table
    // element alone is not enough.
    await expect(page.locator('#lcq_questions table')).toBeVisible();
    await waitForTableSettled(page);
};

/**
 * Waits until the table has finished its AJAX reload.
 *
 * The list repaints itself through a web service call, and during that call the
 * previous - unfiltered - rows are still in the DOM. Counting rows without waiting
 * therefore measured whichever state happened to be rendered at that moment, which
 * showed up as a different test failing on every run.
 *
 * @param page The Playwright page.
 */
export const waitForTableSettled = async(page: Page): Promise<void> => {
    const spinner = page.locator('#lcq_questions .wb-table-call-spinner');
    if (await spinner.count() > 0) {
        await expect(spinner.first()).toBeHidden();
    }
    // Either rows or the explicit "no records" message must be present. Waiting for
    // one of the two is what distinguishes "finished and empty" from "still loading".
    await expect(
        page.locator('#lcq_questions table tbody tr, #lcq_questions .norecordsfound').first()
    ).toBeVisible();
};

/**
 * Returns the locator for the question list rows.
 *
 * @param page The Playwright page.
 */
export const questionRows = (page: Page) => page.locator('#lcq_questions table tbody tr');

/**
 * Returns the locator for the "no records found" message.
 *
 * @param page The Playwright page.
 */
export const noRecordsMessage = (page: Page) => page.locator('#lcq_questions .norecordsfound');

/**
 * Submits the question text search and waits for the list to settle.
 *
 * @param page The Playwright page.
 * @param term The search term.
 */
export const searchQuestionText = async(page: Page, term: string): Promise<void> => {
    await page.fill('#catquiz-qtsearch', term);
    await page.click('#catquiz-questiontext-search button[type="submit"]');
    await expect(page.locator('#lcq_questions')).toBeVisible();
    await waitForTableSettled(page);
};
