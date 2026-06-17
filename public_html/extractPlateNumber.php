<?php
  function extractPlateNumber($inputString) {

    // Clean up removing any crap characters 
    $inputString = preg_replace('/[^a-zA-Z0-9 ]/', '', $inputString);
    
    // Extract the last matching word from the input string
    $pattern = '/\b[A-Z0-9- ]{1,12}\b/';

    // Perform the match for all occurrences
    if (preg_match_all($pattern, $inputString, $matches)) {
        $allMatches = $matches[0]; // Get all matched plate numbers
        $lastMatch = end($allMatches); // Get the last matched plate number

        // Remove all hyphens from the last match
        $lastMatch = str_replace('-', '', $lastMatch);
        $lastMatch = str_replace(' ', '', $lastMatch);

        // Check if the last match is less than 4 characters
        while (strlen($lastMatch) < 4 && count($allMatches) > 1) {
            $previousMatch = str_replace('-', '', $allMatches[count($allMatches) - 2]);
            return $previousMatch . $lastMatch; // Prefix the previous match
        }

        $lastMatch = preg_replace('/[^A-Za-z0-9]/', '', $lastMatch);
        $lastMarch = trim($lastMatch);
        return $lastMatch; // Return the processed last match
    }

    // Return null if no match is found
    return null;
}

