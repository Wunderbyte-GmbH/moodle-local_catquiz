  /**
    * Create items in a new context.
    *
    * For each item parameter in the new context:
    * 1. Get the corresponding item from either context
    * 2. If an item is in active use in the old context (used by a test), create a new item (copy).
    * 3. Otherwise update the existing item with new context and active parameter
    * 4. Update all parameters to point to the correct item
    *
    * @param int $newcontextid The ID of the context to create items in
    * @param ?int $oldcontextid The ID of the old context, if any
    * @return void
    */
public function create_items_in_new_context(int $newcontextid, ?int $oldcontextid): void {
    global $DB;
    // Copy item parameters that are present in the old context but not in
    // the new one from old context to the new.
    // E.g., if we fetched 100 params from the central instance but locally we have 150, copy
    // the remaining 50 params.
    if ($oldcontextid) {
        $remainingparams = $this->get_params_from_old_context($oldcontextid, $newcontextid);
        $remainingparams = array_map(
            function ($param) use ($newcontextid) {
                $param->contextid = $newcontextid;
                $param->id = null;
                return $param;
            },
            $remainingparams
        );
        $DB->insert_records('local_catquiz_itemparams', $remainingparams);
    }

    // Decide if we should just replace existing items with the new contextid or create new items.
    $createnew = false;
    if (!$oldcontextid || $this->is_active_context($oldcontextid)) {
        $createnew = true;
    }

    // Create a mapping of questionid -> item.
    // If it exists in both old and new context, the mapping maps to the new context.
    $qid2item = [];
    $contextids = $oldcontextid
        ? array_reverse(range($oldcontextid, $newcontextid))
        : [$newcontextid];
    [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'contextid');
    foreach ($DB->get_records_select('local_catquiz_items', "contextid $insql", $inparams) as $i) {
        if (!isset($qid2item[$i->componentid]) || $i->contextid > $qid2item[$i->componentid]->contextid) {
            $qid2item[$i->componentid] = $i;
        }
    }

    $activeparam = [];
    $transaction = $DB->start_delegated_transaction();
    $newparams = $DB->get_records('local_catquiz_itemparams', ['contextid' => $newcontextid]);
    $qid2params = [];
    foreach ($newparams as $np) {
        $qid2params[$np->componentid][] = $np;
    }

    $tosave = [];

    foreach ($newparams as $ip) {
        $questionid = $ip->componentid;
        $item = $qid2item[$questionid];

        if ($createnew && $item->contextid == $oldcontextid) {
            unset($item->id);
        }

        // For each itemparam in the new context, get the one with the
        // highest `status` value. If there a multiple, pick the first one.
        if (
            !array_key_exists($questionid, $activeparam)
            || $ip->status > $activeparam[$questionid]->status
        ) {
            $activeparam[$questionid] = $ip;
            $item->activeparamid = $ip->id;
            $tosave[$questionid] = $item;
        }
    }

    foreach ($tosave as $questionid => $item) {
        if ($createnew && $item->contextid == $oldcontextid) {
            $item->contextid = $newcontextid;
            $itemid = $DB->insert_record('local_catquiz_items', $item, true);
        }
        else {
            // This item is already in the database because it was 1)
            // inserted when itemparams were fetched or 2) it existed
            // already and will be udpated.
            $item->contextid = $newcontextid;
            $DB->update_record('local_catquiz_items', $item);
            $itemid = $item->id;
        }
        foreach ($qid2params[$questionid] as $p) {
            $p->itemid = $itemid;
            $DB->update_record('local_catquiz_itemparams', $p, true);
        }
    }
    $DB->commit_delegated_transaction($transaction);
}