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
    // If the context of the scale was changed, we have to update the active item params.
    if ($oldrecord->contextid != $catscale->contextid) {
        $repo = new catquiz();
        $repo->create_items_in_new_context($catscale->contextid, $oldrecord->contextid);
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