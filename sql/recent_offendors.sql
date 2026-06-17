SELECT distinct al.plate, count(1) as count, group_concat(phototime) as times from (
SELECT p1.plate, p1.uploadFile, p1.phototime
FROM parking_records p1
WHERE p1.plate IN (
    SELECT p2.plate
    FROM (
        SELECT plate, COUNT(DISTINCT checksum) AS count
        FROM parking_records
        GROUP BY plate
    ) p2
    WHERE p2.count > 4
)
AND p1.plate IN (
    SELECT plate
    FROM parking_records
    WHERE phototime >= NOW() - INTERVAL 3 DAY
)
GROUP BY p1.checksum
ORDER BY p1.plate, p1.phototime desc) al group by plate order by phototime desc;
