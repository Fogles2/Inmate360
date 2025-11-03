<?php
/**
 * JailTrak - Helper Functions
 * Place global utility functions here.
 */

function jailtrak_format_date($date)
{
    return date('M j, Y', strtotime($date));
}
?>