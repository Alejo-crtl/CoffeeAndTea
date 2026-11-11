# ============================================================
#  CoffeeAndtea - Preparar entorno de QA
#  Recrea la base coffeeandtea_qa con schema.sql + seed-demo.sql.
#  NUNCA toca la base original CoffeeAndTea.
#
#  Uso:
#    $env:PGPASSWORD='tu_clave'
#    powershell -ExecutionPolicy Bypass -File qa\scripts\preparar-bd.ps1
# ============================================================
param(
    [string]$PgBin = 'C:\Program Files\PostgreSQL\18\bin',
    [string]$DbHost = '127.0.0.1',
    [string]$DbPort = '5432',
    [string]$DbUser = 'postgres',
    [string]$DbName = 'coffeeandtea_qa'
)

$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$psql = Join-Path $PgBin 'psql.exe'

if (-not (Test-Path $psql)) {
    throw "No se encontro psql.exe en $psql. Ajusta el parametro -PgBin."
}
if (-not $env:PGPASSWORD) {
    throw 'Define $env:PGPASSWORD antes de ejecutar este script.'
}
if ($DbName -notmatch '^[a-z0-9_]+$') {
    throw "Nombre de base no valido: $DbName (solo minusculas, numeros y guion bajo)."
}

Write-Host "[1/3] Eliminando base $DbName si existe..."
& $psql -U $DbUser -h $DbHost -p $DbPort -d postgres -q -v ON_ERROR_STOP=1 -c "DROP DATABASE IF EXISTS $DbName;"
if ($LASTEXITCODE -ne 0) { throw 'No se pudo eliminar la base QA.' }

Write-Host "[2/3] Creando base $DbName..."
& $psql -U $DbUser -h $DbHost -p $DbPort -d postgres -q -v ON_ERROR_STOP=1 -c "CREATE DATABASE $DbName;"
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la base QA.' }

Write-Host '[3/3] Cargando esquema y datos demo...'
& $psql -U $DbUser -h $DbHost -p $DbPort -d $DbName -q -v ON_ERROR_STOP=1 -f (Join-Path $raiz 'db\schema.sql')
if ($LASTEXITCODE -ne 0) { throw 'Fallo schema.sql.' }
& $psql -U $DbUser -h $DbHost -p $DbPort -d $DbName -q -v ON_ERROR_STOP=1 -f (Join-Path $raiz 'db\seed-demo.sql')
if ($LASTEXITCODE -ne 0) { throw 'Fallo seed-demo.sql.' }

Write-Host ''
Write-Host "Base $DbName lista para pruebas."
Write-Host 'Usuarios demo: admin@coffeeandtea.local (ADMIN, Admin123!) / encargado@coffeeandtea.local (Encargado123!) / cliente@coffeeandtea.local (Cliente123!)'
