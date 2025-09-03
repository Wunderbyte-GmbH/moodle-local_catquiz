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

    // 1) Fehlende Params aus altem Kontext in den neuen kopieren.
    if ($oldcontextid) {
        $remainingparams = $this->get_params_from_old_context($oldcontextid, $newcontextid);
        if (!empty($remainingparams)) {
            foreach ($remainingparams as $param) {
                $param->contextid = $newcontextid;
                unset($param->id);
            }
            $DB->insert_records('local_catquiz_itemparams', $remainingparams);
        }
    }

    // 2) Entscheiden, ob neue Items angelegt werden müssen.
    $createnew = (!$oldcontextid || $this->is_active_context($oldcontextid));

    // 3) Items NUR aus den relevanten Contexts laden (kein range()!).
    $contextids = [$newcontextid];
    if ($oldcontextid) { $contextids[] = $oldcontextid; }
    [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

    $qid2item = [];
    foreach ($DB->get_records_select('local_catquiz_items', "contextid $insql", $inparams) as $i) {
        // Bevorzuge das Item aus dem neuen Kontext, falls vorhanden.
        if (!isset($qid2item[$i->componentid]) || $i->contextid == $newcontextid) {
            $qid2item[$i->componentid] = $i;
        }
    }

    // 4) TX starten (und garantiert schließen).
    $tx = $DB->start_delegated_transaction();
    try {
        $newparams = $DB->get_records('local_catquiz_itemparams', ['contextid' => $newcontextid]) ?: [];

        // Params je Frage sammeln.
        $qid2params = [];
        foreach ($newparams as $np) {
            $qid2params[$np->componentid][] = $np;
        }

        $tosave = [];        // questionid => item-Objekt (neu/zu aktualisieren)
        $activeparam = [];   // questionid => bestes Param-Objekt

        foreach ($newparams as $ip) {
            $qid  = $ip->componentid;
            $item = $qid2item[$qid] ?? null;

            // Falls gar kein Item existiert: Stub anlegen, wir insert'en später.
            if (!$item) {
                $item = (object)[
                    'componentid'   => $qid,
                    'contextid'     => $newcontextid,
                    // weitere Defaultfelder falls nötig …
                ];
            } else if ($createnew && $item->contextid == $oldcontextid) {
                // Neues Item im neuen Kontext anlegen.
                unset($item->id);
                $item->contextid = $newcontextid;
            } else {
                // Sicherstellen, dass Context auf den neuen zeigt.
                $item->contextid = $newcontextid;
            }

            // Aktivstes Param pro Frage wählen (höchster Status).
            if (!isset($activeparam[$qid]) || $ip->status > $activeparam[$qid]->status) {
                $activeparam[$qid] = $ip;
                $item->activeparamid = $ip->id;   // <-- jetzt garantiert kein null-$item
                $tosave[$qid] = $item;
            }
        }

        // 5) Items speichern und alle Params korrekt auf das Item zeigen lassen.
        foreach ($tosave as $qid => $item) {
            if (empty($item->id)) {
                $itemid = $DB->insert_record('local_catquiz_items', $item, true);
            } else {
                $DB->update_record('local_catquiz_items', $item);
                $itemid = $item->id;
            }

            // Alle Params dieser Frage im neuen Kontext auf das Item mappen.
            foreach ($qid2params[$qid] ?? [] as $p) {
                if ($p->itemid != $itemid) {
                    $p->itemid = $itemid;
                    $DB->update_record('local_catquiz_itemparams', $p);
                }
            }
        }

        // Transaktion sauber beenden.
        $tx->allow_commit();
    }
    catch (\Throwable $e) {
        $tx->rollback($e);
        throw $e;
    }
}