<?php
/**
 * ─────────────────────────────────────────────────────────────────────────────
 * FILE BROADCAST — additions to CampaignsController
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * 1. Add the two use-statements to the top of CampaignsController (if missing):
 *
 *      use Illuminate\Support\Facades\Storage;
 *      use League\Csv\Reader;          // league/csv  — OR use the PhpSpreadsheet path below
 *
 * 2. Add parseFile() and the two private helpers anywhere in the class.
 *
 * 3. In store(), prepend the file-broadcast branch shown at the bottom of this
 *    file BEFORE the existing $campaign = $this->provider::create([...]) call.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DEPENDENCIES
 * ─────────────────────────────────────────────────────────────────────────────
 * For CSV:   built-in fgetcsv — no extra package needed.
 * For XLSX:  composer require phpoffice/phpspreadsheet   (already present in
 *            many Laravel projects; comment/uncomment the block below as needed)
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ════════════════════════════════════════════════════════════════════════════
// SECTION A — parseFile()   (new public method)
// ════════════════════════════════════════════════════════════════════════════

    /**
     * POST /campaigns/parse-file
     *
     * Accepts a CSV or XLSX upload, returns JSON:
     *   { headers: string[], rows: string[][], row_count: int }
     *
     * Called by the file broadcast view via fetch() so it can populate the
     * phone-column picker and variable-column mappers without a page reload.
     *
     * For CSV the client-side JS parser handles it directly; this endpoint is
     * the fallback for XLSX (binary format).
     */
  