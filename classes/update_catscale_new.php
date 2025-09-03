/**
* Update a catscale and invalidate cache.
*
* @param catscale_structure|stdClass $catscale
* @return bool
*/
public static function update_catscale($catscale): bool {
    global $DB, $USER;
    if (!isset($catscale->id)) {
        throw new moodle_exception('noidset', 'local_catquiz');
    }

    $oldrecord = $DB->get_record('local_catquiz_catscales', ['id' => $catscale->id]);
    // Falls Datensatz fehlt, lieber fail-fast mit klarer Meldung:
    if (!$oldrecord) {
        debugging("local_catquiz: update_catscale: scale {$catscale->id} not found", DEBUG_DEVELOPER);
        return false;
    }

    // Nur wenn alter Kontext vorhanden UND anders ist.
    if (!empty($oldrecord->contextid) && $oldrecord->contextid != $catscale->contextid) {
        $repo = new catquiz();
        try {
            $repo->create_items_in_new_context($catscale->contextid, $oldrecord->contextid);
        }
        catch (\Throwable $e) {
            debugging(
                "local_catquiz: create_items_in_new_context failed for scale {$catscale->id} ".
                "(new={$catscale->contextid}, old={$oldrecord->contextid}): ".$e->getMessage(),
                DEBUG_DEVELOPER
            );
            // „Best effort“: nicht hart abbrechen, damit Cron durchläuft.
        }
    }

    $result = $DB->update_record('local_catquiz_catscales', $catscale);

    $context = context_system::instance();

    $event = catscale_updated::create([
        'objectid' => $catscale->id,
        'context' => $context,
        'userid' => $USER->id, // The user who did cancel.
        'other' => [
            'catscaleid' => $catscale->id,
        ],
    ]);
    $event->trigger();

    // Invalidate cache. TODO: Instead of invalidating cache, delete and add the item from the cache.
    $cache = cache::make('local_catquiz', 'catscales');
    $cache->delete('allcatscales');
    $cache->delete($catscale->id);
    return $result;
}