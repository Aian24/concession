<?php
$users = [
    "guiamal", "piamonte", "cabiles", "senorio", "vacant", 
    "aquino", "dongiapon", "turtal", "dit", "francisco", 
    "estosos", "geneva", "gabia", "dejumo", "romero", 
    "mayores", "barcebal", "elbo", "delossantos", "ambos", 
    "madrideo", "rodillo", "dime", "ihong", "villazana", 
    "alarcon", "mercado", "joan", "carlos", "mahinay", 
    "caballes", "baldos", "dail", "malabago", "cacho", 
    "bier", "manuel", "ormillo", "garcia", "llave", 
    "avenido", "dolosa", "zambale", "mangaoang", "almojuela", 
    "dela justa", "delos reyes", "garon", "halili", "zanoria", 
    "tolosa", "vidal"
];

$sql = "INSERT INTO users (username, password, role) VALUES\n";
$values = [];

// Handle duplicate 'garcia' mentioned in the list
$users = array_unique($users);

foreach ($users as $u) {
    $hashed = password_hash($u, PASSWORD_DEFAULT);
    $values[] = "('" . addslashes($u) . "', '" . $hashed . "', 'user')";
}

$sql .= implode(",\n", $values) . ";\n";

file_put_contents('insert_users.sql', $sql);
echo "SQL script generated successfully at scratch/insert_users.sql\n";
?>
