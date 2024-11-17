<?php
session_start();
include("../includes/header.php");
include("../databaseCon/db-connection.php");

// Boolean flag to toggle between cache or database
$useCache = false;
ini_set('display_errors', 1);
error_reporting(E_ALL);


// Cache file location
$cacheFile = "../cache/stations.json";
$stations = [];

// Check if cache file exists and is recent
if ($useCache && file_exists($cacheFile) && time() - filemtime($cacheFile) < 3600) { // 1 hour freshness
    $stations = json_decode(file_get_contents($cacheFile), true);
    error_log("Using cached data");
} else {
    $conn = include("../databaseCon/db-connection.php");

    // Fetch all station details
    $sql_stations = "SELECT Station_ID, Station_Name, Latitude, Longitude, Startvorgaenge, Endvorgaenge FROM stations";
    $stmt_stations = $conn->prepare($sql_stations);
    $stmt_stations->execute();
    $result_stations = $stmt_stations->get_result();

    // SQL for most popular destination
    $sql_popular_destination = "SELECT Ende_Station, COUNT(*) AS count
                                FROM routes
                                WHERE Start_Station_ID = ?
                                GROUP BY Ende_Station
                                ORDER BY count DESC
                                LIMIT 1";
    $stmt_popular = $conn->prepare($sql_popular_destination);

    // SQL for end nodes
    $sql_end_nodes = "SELECT DISTINCT Ende_Station
                      FROM routes
                      WHERE Start_Station_ID = ?";
    $stmt_end_nodes = $conn->prepare($sql_end_nodes);

    while ($row = $result_stations->fetch_assoc()) {
        $row['Latitude'] = (float)$row['Latitude'];
        $row['Longitude'] = (float)$row['Longitude'];
        $row['Startvorgaenge'] = (int)$row['Startvorgaenge'];
        $row['Endvorgaenge'] = (int)$row['Endvorgaenge'];

        // Fetch the most popular destination
        $stmt_popular->bind_param("i", $row['Station_ID']);
        $stmt_popular->execute();
        $result_popular = $stmt_popular->get_result();
        $popular_destination = $result_popular->fetch_assoc();
        $row['Most_Popular'] = $popular_destination ? htmlspecialchars($popular_destination['Ende_Station']) : "N/A";

        // Fetch end nodes
        $stmt_end_nodes->bind_param("i", $row['Station_ID']);
        $stmt_end_nodes->execute();
        $result_end_nodes = $stmt_end_nodes->get_result();
        $end_nodes = [];
        while ($end_node = $result_end_nodes->fetch_assoc()) {
            $end_nodes[] = htmlspecialchars($end_node['Ende_Station']);
        }
        $row['End_Nodes'] = $end_nodes;

        $stations[] = $row;
    }

    $stmt_stations->close();
    $stmt_popular->close();
    $stmt_end_nodes->close();
    $conn->close();

    // Cache the station data
    file_put_contents($cacheFile, json_encode($stations, JSON_PRETTY_PRINT));
    error_log("Cache updated");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Interaktive Karte von Frankfurt</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map {
            height: 500px;
            margin: 20px 0;
        }
        #sidebar {
            position: absolute;
            top: 10%;
            left: 10%;
            width: 300px;
            background-color: #f9f9f9;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
            padding: 15px;
            overflow-y: auto;
            z-index: 1000;
            display: none; /* Hidden by default */
        }
        #closeSidebar {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 18px;
            cursor: pointer;
        }
        #details {
            font-size: 14px;
            line-height: 1.6;
        }
        .highlighted-marker {
            color: red;
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <h2>Station Details</h2>
        <button id="closeSidebar" onclick="resetMarkers()">X</button>
        <div id="details">
            <p>Select a station for details.</p>
        </div>
    </div>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([50.1109, 8.6821], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Kartendaten © <a href="https://www.openstreetmap.org/">OpenStreetMap</a>-Mitwirkende',
        }).addTo(map);

        let markerObjects = [];
        let selectedMarker = null;

        fetch("../cache/stations.json")
            .then(response => response.json())
            .then(stations => {
                stations.forEach(function(station) {
                    const marker = L.marker([station.Longitude, station.Latitude], {
                        stationData: station
                    }).addTo(map);

                   
                    // Mouseover: Show tooltip with most popular destination
                    marker.on('mouseover', function () {
                        marker.bindTooltip(` ${station.Station_Name}`).openTooltip();
                    });

                    // Click: Show details in the sidebar
                    marker.on('click', function () {
                        const sidebar = document.getElementById("sidebar");
                        sidebar.style.display = "block";

                        // Highlight the selected marker
                        if (selectedMarker) {
                            selectedMarker.setOpacity(1);
                        }
                        selectedMarker = marker;
                        marker.setOpacity(1);

                        // Filter out unconnected markers
                        const endNodes = station.End_Nodes || [];
                        markerObjects.forEach(m => {
                            if (!endNodes.includes(m.options.stationData.Station_Name)) {
                                m.setOpacity(0.2);
                            } else {
                                m.setOpacity(1);
                            }
                        });

                        // Populate sidebar with details
                        document.getElementById("details").innerHTML = `
                            <h3>${station.Station_Name}</h3>
                            <p><strong>Station ID:</strong> ${station.Station_ID}</p>
                            <p><strong>Start Vorgänge:</strong> ${station.Startvorgaenge}</p>
                            <p><strong>End Vorgänge:</strong> ${station.Endvorgaenge}</p>
                            <p><strong>Beliebtestes Ziel:</strong> ${station.Most_Popular}</p>
                        `;
                    });

                    markerObjects.push(marker);
                });
            })
            .catch(error => console.error("Error fetching stations:", error));

        function resetMarkers() {
            document.getElementById("sidebar").style.display = "none";
            markerObjects.forEach(marker => marker.setOpacity(1));
            selectedMarker = null;
        }
    </script>
    <include src="../includes/footer.php"></include>
</body>
</html>
