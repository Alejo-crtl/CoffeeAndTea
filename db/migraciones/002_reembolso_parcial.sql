-- ============================================================
-- CoffeeAndtea - Actualización: reembolso parcial
--
-- Agrega el monto reembolsado al pago para distinguir un reembolso
-- del 100 % (antes de preparación) de uno del 50 % (en preparación
-- o listo).
--
-- Uso:
--   psql -U postgres -h 127.0.0.1 -d CoffeeAndTea `
--        -v ON_ERROR_STOP=1 -f db/migraciones/002_reembolso_parcial.sql
-- ============================================================

BEGIN;

ALTER TABLE pago
    ADD COLUMN IF NOT EXISTS monto_reembolsado NUMERIC(12,2)
        CHECK (monto_reembolsado IS NULL OR monto_reembolsado >= 0);

-- Los reembolsos históricos se registraron por el monto total del pago.
UPDATE pago
SET monto_reembolsado = monto
WHERE estado = 'REEMBOLSADO' AND monto_reembolsado IS NULL;

COMMIT;

\echo 'Actualización aplicada: pago.monto_reembolsado disponible.'
