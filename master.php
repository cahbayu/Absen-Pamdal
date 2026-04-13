<?php
// receive-data.php

// Set header untuk menerima JSON
header("Content-Type: application/json");

// Ambil data POST
$data = json_decode(file_get_contents("php://input"), true);

if ($data) {
    // Koneksi ke database
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "esp32";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Iterasi melalui setiap slave dan sensor data
    foreach ($data['slaves'] as $slave) {
        $slave_id = $slave['slave_id'];
        
        foreach ($slave['sensor_data'] as $sensor) {
            $sensor_id = $sensor['sensor_id'];
            $moisture_value = $sensor['moisture_value'];
            $timestamp = $sensor['timestamp'];

            // Query untuk menyimpan data ke database
            $sql = "INSERT INTO sensor_data (slave_id, sensor_id, moisture_value, timestamp)
                    VALUES ('$slave_id', '$sensor_id', '$moisture_value', '$timestamp')";

            if ($conn->query($sql) === TRUE) {
                echo "New record created successfully";
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        }
    }

    $conn->close();

} else {
    echo "No data received!";
}
?>
