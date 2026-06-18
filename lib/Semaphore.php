<?php

class Semaphore {
    private static $lockFile;

    public static function acquire() {
        if (!self::$lockFile) {
            self::$lockFile = fopen(__DIR__ . "/../var/api_call_semaphore.lock", "w+");
        }
        return flock(self::$lockFile, LOCK_EX);
    }

    public static function release() {
        if (self::$lockFile) {
            sleep(2); /* So we don't do this too often - ChatGPT starts grouping things */
            flock(self::$lockFile, LOCK_UN);
            fclose(self::$lockFile);
            self::$lockFile = null;
        }
    }
}