<?php

namespace App\Enums;

enum FormaDePago: string
{
    case BIZUM = 'Bizum';
    case CHEQUE = 'Cheque';
    case CONFIRMING = 'Confirming';
    case CONTADO = 'Contado';
    case CREDITO = 'Crédito';
    case DEVOLUCION_DEPOSITO = 'Devolución depósito';
    case DEVOLUCION_REMESA = 'Devolución remesa';
    case INGRESO_CUENTA = 'Ingreso en cuenta';
    case PAGARE = 'Pagaré';
    case PAYONEER = 'Payoneer';
    case PAYPAL = 'PayPal';
    case RECIBO_DOMICILIADO = 'Recibo domiciliado';
    case REMESA_BANCARIA = 'Remesa bancaria';
    case TARJETA_CREDITO = 'Tarjeta de crédito';
    case TARJETA_DEBITO = 'Tarjeta de débito';
    case TRANSFERENCIA = 'Transferencia bancaria';

    public function label(): string
    {
        return $this->value;
    }
}
