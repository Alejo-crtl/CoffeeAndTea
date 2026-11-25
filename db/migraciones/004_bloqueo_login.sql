-- 004 · Bloqueo de login por cuenta
--
-- El bloqueo anterior vivía en la sesión, así que se evadía borrando la cookie.
-- Ahora se guarda en la base de datos y aplica a cualquier navegador.

ALTER TABLE usuario
    ADD COLUMN IF NOT EXISTS intentos_fallidos INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS bloqueado_hasta TIMESTAMPTZ;

COMMENT ON COLUMN usuario.intentos_fallidos IS 'Fallos consecutivos desde el último acceso correcto.';
COMMENT ON COLUMN usuario.bloqueado_hasta IS 'Hasta esta marca de tiempo el acceso está bloqueado.';

-- Los usuarios existentes empiezan limpios.
UPDATE usuario
SET intentos_fallidos = 0,
    bloqueado_hasta = NULL
WHERE intentos_fallidos <> 0
   OR bloqueado_hasta IS NOT NULL;
