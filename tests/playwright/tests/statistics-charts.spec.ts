import {expect, test} from '@playwright/test';
import {statsPageCmid} from '../support/env';
import {login} from '../support/catmanager';

/**
 * The statistics charts (issue #23).
 *
 * The charts are rendered by the [catquizstatistics] shortcode on a course page, not
 * by the CAT manager. What a unit test cannot show is that the aggregated numbers
 * actually arrive in the browser as a chart: the aggregation happens in SQL now, and
 * a mistake there would surface as an empty or broken chart rather than as a failing
 * assertion on a PHP array.
 */
test.describe('CAT statistics charts', () => {
    test.beforeEach(async({page}) => {
        await login(page);
        await page.goto(`/mod/page/view.php?id=${statsPageCmid()}`);
    });

    test('renders the statistics instead of the raw shortcode', async({page}) => {
        // If filter_shortcodes were inactive the page would show the literal text.
        // That is the failure mode this assertion exists for.
        await expect(page.locator('body')).not.toContainText('[catquizstatistics');
    });

    test('shows a chart rather than the no-data placeholder', async({page}) => {
        // The seed provides attempts, so the charts must have something to draw.
        // Without this the shortcode would render its "no data" state and every
        // other assertion here would pass without exercising the aggregation.
        //
        // Moodle's chart API emits a .chart-area container and fills the canvas from
        // JavaScript, so waiting for the canvas alone would race the script. The
        // container is what the server produced; the canvas proves the script ran.
        const area = page.locator('.chart-area').first();
        await expect(area).toBeVisible({timeout: 30_000});
        await expect(area.locator('canvas')).toBeVisible({timeout: 30_000});
    });

    test('does not report an error from the shortcode', async({page}) => {
        // populate_arguments() throws for a missing scale or a conflicting course,
        // and the shortcode then renders the message instead of the charts. A chart
        // that is simply absent would otherwise look like a rendering delay.
        const main = page.locator('#region-main');
        await expect(main).not.toContainText('catquizstatistics_askforparams');
        await expect(main).not.toContainText('scale_course_conflict');
    });
});

