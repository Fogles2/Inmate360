<?php
/**
 * JailTrak - Database Helper
 * Provides a PDO connection
 */

function get_db()
{
    static $db;
    if (!$db) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}
?>