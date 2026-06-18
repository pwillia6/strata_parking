<?php

/**
 * A simple file-based semaphore to ensure that only one process
 * can execute a critical section of code at a time. This is used
 * to prevent concurrent calls to the OpenAI API.
 */
class Semaphore {
    /**
     * @var resource|null The file handle for the lock file.
     */
    private static $lockFile;

    /**
     * Acquires an exclusive lock.
     * @return bool True on success, false on failure.
     */
    public static function acquire() {
        if (!self::$lockFile) {
            self::$lockFile = fopen(__DIR__ . "/../var/api_call_semaphore.lock", "w+");
        }
        return flock(self::$lockFile, LOCK_EX);
    }

    /**
     * Releases the lock.
     */
    public static function release() {
        if (self::$lockFile) {
            sleep(2); /* So we don't do this too often - ChatGPT starts grouping things */
            flock(self::$lockFile, LOCK_UN);
            fclose(self::$lockFile);
            self::$lockFile = null;
        }
    }
}