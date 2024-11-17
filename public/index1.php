<?php
session_start();
include ("../includes/header.php");
include ("../includes/SQLfunctions.php");
?>
<body>
    <nav>
        <ul>
            <li><a href="?display=stations">Stationen</a></li>
            <li><a href="?display=users">Benutzer</a></li>
            <li><a href="?display=bikes">Fahrräder</a></li>
        </ul>
    </nav>
    <div class="content">
        <?php
        // Überprüfen, ob display=stations in der URL steht
        if (isset($_GET['display']) && $_GET['display'] == 'stations') {
            displayStations();
        } elseif (isset($_GET['display']) && $_GET['display'] == 'users') {
            displayUser();
        } elseif (isset($_GET['display']) && $_GET['display'] == 'bikes') {
            displayBikeDetails();
        }
        ?>
    </div>
</body>

<?php include("../includes/footer.php") ?>