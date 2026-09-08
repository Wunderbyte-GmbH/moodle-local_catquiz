/**
 * Access to the values the seed script exported.
 *
 * Every getter throws when its variable is missing. A test that silently ran
 * against `undefined` would fail somewhere deep in the browser with a message that
 * says nothing about the real cause - a forgotten seed step.
 */

/**
 * Returns a required environment variable.
 *
 * @param name The variable name.
 */
const required = (name: string): string => {
    const value = process.env[name];
    if (!value) {
        throw new Error(
            `Missing environment variable ${name}. Run seed.php from the Moodle root and source its output:\n` +
            `  eval "$(php local/catquiz/tests/playwright/seed.php)"`
        );
    }
    return value;
};

export const baseUrl = (): string => process.env.CATQUIZ_BASE_URL || 'http://127.0.0.1:8000';

export const scaleId = (): string => required('CATQUIZ_SCALEID');
export const contextId = (): string => required('CATQUIZ_CONTEXTID');
export const matchingTerm = (): string => required('CATQUIZ_MATCHING_TERM');
export const missingTerm = (): string => required('CATQUIZ_MISSING_TERM');
export const matchingQuestion = (): string => required('CATQUIZ_MATCHING_QUESTION');
export const unusableQuestion = (): string => required('CATQUIZ_UNUSABLE_QUESTION');
export const pilotQuestion = (): string => required('CATQUIZ_PILOT_QUESTION');
export const statsPageCmid = (): string => required('CATQUIZ_STATSPAGE_CMID');
export const adminUser = (): string => process.env.CATQUIZ_ADMIN_USER || 'admin';
export const adminPass = (): string => required('CATQUIZ_ADMIN_PASS');
