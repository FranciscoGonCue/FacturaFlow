<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Excepción base de la aplicación. Todas las reglas de negocio que fallan heredan de ella,
 * así un solo catch (FacturaFlowException $e) captura cualquier error "esperado" del dominio.
 */
abstract class FacturaFlowException extends Exception {}
