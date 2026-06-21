<?php

/**
 * Manages the application's database connection using a singleton pattern.
 * Provides a centralized set of methods for all database interactions.
 */
class Database {
    /**
     * @var mysqli|null The singleton mysqli connection object.
     */
    private static $conn;

    /**
     * Gets the singleton database connection, creating it if it doesn't exist.
     *
     * @return mysqli
     * @throws Exception if the connection fails.
     */
    public static function getConnection() {
        if (self::$conn === null) {
            self::$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if (self::$conn->connect_error) {
                throw new Exception("Database connection failed: " . self::$conn->connect_error);
            }
        }
        return self::$conn;
    }

    /**
     * Closes the singleton database connection if it is open.
     */
    public static function close() {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }

    /**
     * Private helper to execute a prepared statement.
     *
     * @param string $sql The SQL query with placeholders.
     * @param array $params The parameters to bind.
     * @param string $types A string containing the types for each parameter.
     * @return mysqli_stmt The executed statement object.
     * @throws Exception
     */
    private static function executeQuery($sql, $params, $types) {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        return $stmt;
    }

    // --- Parking Records ---

    /**
     * Inserts a new record into the parking_records table.
     *
     * @param string $plate
     * @param string $containscar
     * @param string $containsvisitor
     * @param string $containsnumber
     * @param string $result
     * @param string $uploadFile
     * @param string $phototime
     * @param string $checksum
     * @param string|null $uuid
     * @throws Exception
     */
    public static function insertParkingRecord($plate, $containscar, $containsvisitor, $containsnumber, $result, $uploadFile, $phototime, $checksum, $uuid) {

        if ($plate === null) {
            // DB Will not accept record
            return;
        } 

        $sql = "INSERT INTO parking_records (plate, containscar, containsvisitor, containsnumber, result, uploadFile, phototime, `checksum`, `uuid`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = self::executeQuery($sql, [$plate, $containscar, $containsvisitor, $containsnumber, $result, $uploadFile, $phototime, $checksum, $uuid], "sssssssss");
        $stmt->close();
    }

    /**
     * Retrieves the uploadFile path for a specific parking record by its ID.
     * @param int $id
     * @return string
     * @throws Exception
     */
    public static function getParkingRecordUploadFileById($id) {
        $sql = "SELECT uploadFile FROM parking_records WHERE id = ?";
        $stmt = self::executeQuery($sql, [$id], "i");
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            throw new Exception("No record found in parking_records with ID: $id");
        }
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row['uploadFile'];
    }

    /**
     * Updates an existing parking record.
     *
     * @param int $id
     * @param string $plate
     * @param string $containscar
     * @param string $containsvisitor
     * @param string $containsnumber
     * @param string $result
     * @param string $checksum
     * @throws Exception
     */
    public static function updateParkingRecord($id, $plate, $containscar, $containsvisitor, $containsnumber, $result, $checksum) {
        $sql = "UPDATE parking_records SET plate = ?, containscar = ?, containsvisitor = ?, containsnumber = ?, result = ?, `checksum`=?, timestamp=timestamp WHERE id=?";
        $stmt = self::executeQuery($sql, [$plate, $containscar, $containsvisitor, $containsnumber, $result, $checksum, $id], "ssssssi");
        $stmt->close();
    }

    /**
     * Gets the history of sightings for a specific license plate from parking_records.
     * @param string $plate
     * @return mysqli_result
     * @throws Exception
     */
    public static function getParkingHistoryByPlate($plate) {
        $sql = "SELECT uploadFile, timestamp FROM parking_records WHERE plate = ?";
        $stmt = self::executeQuery($sql, [$plate], "s");
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    // --- Survey Records ---

    /**
     * Inserts a new record into the survey table.
     *
     * @param string $plate
     * @param string $unit_number
     * @param string $result
     * @param string $uploadFile
     * @param string $phototime
     * @param string $checksum
     * @param string|null $uuid
     * @throws Exception
     */
    public static function insertSurveyRecord($plate, $unit_number, $result, $uploadFile, $phototime, $checksum, $uuid) {
        if ($plate === null) {
            // DB Will not accept record
            return;
        } 

        $sql = "INSERT INTO survey (plate, unitnumber, result, uploadFile, phototime, `checksum`, `uuid`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = self::executeQuery($sql, [$plate, $unit_number, $result, $uploadFile, $phototime, $checksum, $uuid], "sssssss");
        $stmt->close();
    }

    /**
     * Retrieves the uploadFile path for a specific survey record by its ID.
     * @param int $id
     * @return string
     * @throws Exception
     */
    public static function getSurveyUploadFileById($id) {
        $sql = "SELECT uploadFile FROM survey WHERE id = ?";
        $stmt = self::executeQuery($sql, [$id], "i");
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            throw new Exception("No record found in survey with ID: $id");
        }
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row['uploadFile'];
    }

    /**
     * Updates an existing survey record.
     *
     * @param int $id
     * @param string $plate
     * @param string $unit_number
     * @param string $result
     * @param string $checksum
     * @throws Exception
     */
    public static function updateSurveyRecord($id, $plate, $unit_number, $result, $checksum) {
        $sql = "UPDATE survey SET plate = ?, unitnumber=?, result = ?, `checksum`=?, timestamp=timestamp WHERE id=?";
        $stmt = self::executeQuery($sql, [$plate, $unit_number, $result, $checksum, $id], "ssssi");
        $stmt->close();
    }

    /**
     * Gets the history of sightings for a specific license plate from the survey table.
     * @param string $plate
     * @return mysqli_result
     * @throws Exception
     */
    public static function getSurveyHistoryByPlate($plate) {
        $sql = "SELECT uploadFile, unitnumber FROM survey WHERE plate = ?";
        $stmt = self::executeQuery($sql, [$plate], "s");
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    // --- Notice Records ---

    /**
     * Inserts a new record into the notice table.
     *
     * @param string $plate
     * @param string $noticepinned
     * @param string $result
     * @param string $uploadFile
     * @param string $phototime
     * @param string $checksum
     * @param string|null $uuid
     * @throws Exception
     */
    public static function insertNoticeRecord($plate, $noticepinned, $result, $uploadFile, $phototime, $checksum, $uuid) {
        if ($plate === null) {
            // DB Will not accept record
            return;
        } 

        $sql = "INSERT INTO notice (plate, noticepinned, result, uploadFile, phototime, `checksum`, `uuid`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = self::executeQuery($sql, [$plate, $noticepinned, $result, $uploadFile, $phototime, $checksum, $uuid], "sssssss");
        $stmt->close();
    }

    /**
     * Retrieves the uploadFile path for a specific notice record by its ID.
     * @param int $id
     * @return string
     * @throws Exception
     */
    public static function getNoticeUploadFileById($id) {
        $sql = "SELECT uploadFile FROM notice WHERE id = ?";
        $stmt = self::executeQuery($sql, [$id], "i");
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            throw new Exception("No record found in notice with ID: $id");
        }
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row['uploadFile'];
    }

    /**
     * Updates an existing notice record.
     *
     * @param int $id
     * @param string $plate
     * @param string $noticepinned
     * @param string $result
     * @param string $checksum
     * @throws Exception
     */
    public static function updateNoticeRecord($id, $plate, $noticepinned, $result, $checksum) {
        $sql = "UPDATE notice SET plate = ?, noticepinned = ?, result = ?, `checksum`=?, timestamp=timestamp WHERE id=?";
        $stmt = self::executeQuery($sql, [$plate, $noticepinned, $result, $checksum, $id], "ssssi");
        $stmt->close();
    }

    /**
     * Gets the history of sightings for a specific license plate from the notice table.
     * @param string $plate
     * @return mysqli_result
     * @throws Exception
     */
    public static function getNoticeHistoryByPlate($plate) {
        $sql = "SELECT uploadFile, timestamp FROM notice WHERE plate = ?";
        $stmt = self::executeQuery($sql, [$plate], "s");
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
}