<?php

class Database {
    private static $conn;

    /**
     * @return mysqli
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

    public static function close() {
        if (self::$conn !== null) {
            self::$conn->close();
            self::$conn = null;
        }
    }

    /**
     * Private helper to execute a prepared statement.
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

    public static function insertParkingRecord($plate, $containscar, $containsvisitor, $containsnumber, $result, $uploadFile, $phototime, $checksum, $uuid) {
        $sql = "INSERT INTO parking_records (plate, containscar, containsvisitor, containsnumber, result, uploadFile, phototime, `checksum`, `uuid`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = self::executeQuery($sql, [$plate, $containscar, $containsvisitor, $containsnumber, $result, $uploadFile, $phototime, $checksum, $uuid], "sssssssss");
        $stmt->close();
    }

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

    public static function updateParkingRecord($id, $plate, $containscar, $containsvisitor, $containsnumber, $result, $checksum) {
        $sql = "UPDATE parking_records SET plate = ?, containscar = ?, containsvisitor = ?, containsnumber = ?, result = ?, `checksum`=?, timestamp=timestamp WHERE id=?";
        $stmt = self::executeQuery($sql, [$plate, $containscar, $containsvisitor, $containsnumber, $result, $checksum, $id], "ssssssi");
        $stmt->close();
    }

    public static function getParkingHistoryByPlate($plate) {
        $sql = "SELECT uploadFile, timestamp FROM parking_records WHERE plate = ?";
        $stmt = self::executeQuery($sql, [$plate], "s");
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    // --- Survey Records ---

    public static function insertSurveyRecord($plate, $unit_number, $result, $uploadFile, $phototime, $checksum, $uuid) {
        $sql = "INSERT INTO survey (plate, unitnumber, result, uploadFile, phototime, `checksum`, `uuid`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = self::executeQuery($sql, [$plate, $unit_number, $result, $uploadFile, $phototime, $checksum, $uuid], "sssssss");
        $stmt->close();
    }

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

    public static function updateSurveyRecord($id, $plate, $unit_number, $result, $checksum) {
        $sql = "UPDATE survey SET plate = ?, unitnumber=?, result = ?, `checksum`=?, timestamp=timestamp WHERE id=?";
        $stmt = self::executeQuery($sql, [$plate, $unit_number, $result, $checksum, $id], "ssssi");
        $stmt->close();
    }

    public static function getSurveyHistoryByPlate($plate) {
        $sql = "SELECT uploadFile, unitnumber FROM survey WHERE plate = ?";
        $stmt = self::executeQuery($sql, [$plate], "s");
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }

    // --- Notice Records ---

    public static function insertNoticeRecord($plate, $noticepinned, $result, $uploadFile, $phototime, $checksum, $uuid) {
        $sql = "INSERT INTO notice (plate, noticepinned, result, uploadFile, phototime, `checksum`, `uuid`) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = self::executeQuery($sql, [$plate, $noticepinned, $result, $uploadFile, $phototime, $checksum, $uuid], "sssssss");
        $stmt->close();
    }

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

    public static function updateNoticeRecord($id, $plate, $noticepinned, $result, $checksum) {
        $sql = "UPDATE notice SET plate = ?, noticepinned = ?, result = ?, `checksum`=?, timestamp=timestamp WHERE id=?";
        $stmt = self::executeQuery($sql, [$plate, $noticepinned, $result, $checksum, $id], "ssssi");
        $stmt->close();
    }

    public static function getNoticeHistoryByPlate($plate) {
        $sql = "SELECT uploadFile, timestamp FROM notice WHERE plate = ?";
        $stmt = self::executeQuery($sql, [$plate], "s");
        $result = $stmt->get_result();
        $stmt->close();
        return $result;
    }
}