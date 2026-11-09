<?php
/**
 * Runner autónomo de pruebas (no requiere Composer ni PHPUnit).
 *
 * Uso:
 *   $env:DB_NAME='coffeeandtea_qa'
 *   $env:DB_USER='postgres'
 *   $env:DB_PASS='tu_clave'
 *   php tests/run.php
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    fwrite(STDERR, "PHPUnit está instalado. Ejecuta: vendor\\bin\\phpunit\n");
    exit(0);
}

$archivos = array_merge(
    glob(__DIR__ . '/Unit/*Test.php') ?: [],
    glob(__DIR__ . '/Integration/*Test.php') ?: []
);
sort($archivos);

if ($archivos === []) {
    fwrite(STDERR, "No se encontraron archivos de prueba.\n");
    exit(1);
}

$clasesAntes = get_declared_classes();
foreach ($archivos as $archivo) {
    require_once $archivo;
}
$clasesNuevas = array_diff(get_declared_classes(), $clasesAntes);

$total = 0;
$fallidas = 0;

foreach ($clasesNuevas as $clase) {
    $reflexion = new ReflectionClass($clase);
    if (!$reflexion->isSubclassOf(CasoBase::class) || $reflexion->isAbstract()) {
        continue;
    }

    foreach ($reflexion->getMethods(ReflectionMethod::IS_PUBLIC) as $metodo) {
        if (!str_starts_with($metodo->getName(), 'test') || $metodo->getDeclaringClass()->getName() !== $clase) {
            continue;
        }
        $total++;
        $nombre = $clase . '::' . $metodo->getName();
        try {
            $instancia = $reflexion->newInstance();
            $metodo->invoke($instancia);
            echo "  OK   {$nombre}\n";
        } catch (Throwable $e) {
            $fallidas++;
            echo "  FAIL {$nombre}\n";
            echo '       ' . str_replace("\n", "\n       ", $e->getMessage()) . "\n";
        }
    }
}

echo "\n";
echo "Pruebas: {$total} | Aprobadas: " . ($total - $fallidas) . " | Fallidas: {$fallidas}\n";

exit($fallidas === 0 ? 0 : 1);
