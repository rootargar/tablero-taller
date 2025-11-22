<?php
header('Content-Type: text/plain; charset=utf-8');
echo "TEST 1: PHP funciona\n";

if (file_exists('conexion.php')) {
    echo "TEST 2: conexion.php existe\n";

    // Ver el contenido de conexion.php
    $contenido = file_get_contents('conexion.php');
    echo "TEST 3: Tamaño de conexion.php: " . strlen($contenido) . " bytes\n";

    // Intentar incluirlo
    try {
        require_once 'conexion.php';
        echo "TEST 4: conexion.php incluido correctamente\n";

        if (isset($conn)) {
            echo "TEST 5: Variable \$conn existe\n";
            if ($conn === false) {
                echo "TEST 6: \$conn es FALSE\n";
                print_r(sqlsrv_errors());
            } else {
                echo "TEST 6: \$conn es válida\n";
            }
        } else {
            echo "TEST 5: ERROR - Variable \$conn NO existe\n";
        }
    } catch (Exception $e) {
        echo "ERROR al incluir conexion.php: " . $e->getMessage() . "\n";
    }
} else {
    echo "TEST 2: ERROR - conexion.php NO existe\n";
}

echo "\nFIN DE LAS PRUEBAS\n";
?>
