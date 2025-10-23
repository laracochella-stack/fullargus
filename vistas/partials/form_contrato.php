<!-- Sección de datos del contrato -->
<?php
$contratoPrefill = $contratoPrefill ?? [];
$valorContrato = static function (string $campo) use ($contratoPrefill): string {
    return htmlspecialchars((string)($contratoPrefill[$campo] ?? ''), ENT_QUOTES);
};
?>

<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearFolio">Folio</label>
  <input type="text" id="crearFolio" class="form-control form-control-sm text-uppercase" name="folio" required value="<?php echo $valorContrato('folio'); ?>" data-requirement="Captura el folio interno del contrato en mayúsculas. Debe ser único." aria-describedby="crearFolioHint">
  <div id="crearFolioHint" class="form-text ag-field-hint">Puede incluir letras y números (ej. CON-2024-001). Se usará para identificar el contrato.</div>
</div>

<!-- Campo para fecha del contrato y su versión fija -->
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearFechaContrato">Fecha del contrato</label>
  <input type="date" class="form-control form-control-sm" name="fecha_contrato" id="crearFechaContrato" value="<?php echo $valorContrato('fecha_contrato'); ?>" data-requirement="Seleccione la fecha en la que se firma formalmente el contrato." aria-describedby="crearFechaContratoHint">
  <div id="crearFechaContratoHint" class="form-text ag-field-hint">Formato DD-MM-AAAA. Esta fecha alimenta la versión en texto y el cálculo del día de inicio.</div>
  <input type="hidden" name="fecha_contrato_fixed" id="crearFechaContratoFixed" value="<?php echo $valorContrato('fecha_contrato_fixed'); ?>">
  <input type="hidden" name="fecha_contrato_texto" id="crearFechaContratoTexto" value="<?php echo $valorContrato('fecha_contrato_texto'); ?>">
  <!-- Día de inicio (sólo número), calculado desde la fecha del contrato -->
  <input type="hidden" name="dia_inicio" id="crearDiaInicio">
</div>
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearInicioPagos">Inicio de pagos</label>
  <input type="date" name="inicio_pagos" id="crearInicioPagos" class="form-control form-control-sm" required value="<?php echo $valorContrato('inicio_pagos'); ?>" data-requirement="Indique la fecha del primer pago (formato DD-MM-AAAA)." aria-describedby="crearInicioPagosHint">
  <div id="crearInicioPagosHint" class="form-text ag-field-hint">Fecha del primer abono según plan financiero. Se usará para el calendario de pagos.</div>
  <input type="hidden" name="inicio_pagos_texto" id="crearInicioPagosTexto" value="<?php echo $valorContrato('inicio_pagos_texto'); ?>">
</div>
<?php
$fraccionesPrefillRaw = (string)($contratoPrefill['fracciones'] ?? '');
$fraccionesPrefillList = array_filter(array_map('trim', $fraccionesPrefillRaw !== '' ? explode(',', $fraccionesPrefillRaw) : []));
$formSoloLectura = isset($soloLecturaCompleto) && $soloLecturaCompleto;
?>

<div class="col-md-3">
  <label class="form-label ag-keep-label" for="inputFraccionCrear">Fracción vendida/cedida</label>
  <input type="text" class="form-control form-control-sm" id="inputFraccionCrear" placeholder="Ingresa y presiona Enter" data-requirement="Escribe la clave del lote y presiona Enter para agregarla (ej. LOTE 12 MZ B)." aria-describedby="crearFraccionesHint">
  <div id="contenedorFraccionesCrear" class="mt-2<?php echo ($formSoloLectura && !empty($fraccionesPrefillList)) ? ' ag-chip-container' : ''; ?>">
    <?php if ($formSoloLectura && !empty($fraccionesPrefillList)): ?>
      <?php foreach ($fraccionesPrefillList as $fraccion): ?>
        <span class="ag-chip text-uppercase"><?php echo htmlspecialchars($fraccion, ENT_QUOTES); ?></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <label class="form-label mt-2 ag-keep-label">Lotes disponibles:</label>
  <div id="listaFraccionesDisponiblesCrear" class="mt-1" style="font-size:0.8rem;"></div>
  <input type="hidden" name="fracciones" id="hiddenFraccionesCrear" value="<?php echo $valorContrato('fracciones'); ?>">
  <div id="crearFraccionesHint" class="form-text ag-field-hint">Agrega todos los lotes o fracciones vendidos. Puedes eliminar los chips generados si te equivocas.</div>
</div>

<div class="col-md-12">
  <label class="form-label ag-keep-label" for="crearHabitacional">Habitacional y colindancias</label>
  <!-- Campo de texto simple. Se almacenará en mayúsculas -->
  <textarea class="form-control form-control-sm text-uppercase" name="habitacional" id="crearHabitacional" rows="6" data-requirement="Describe el uso del inmueble y las colindancias en mayúsculas." aria-describedby="crearHabitacionalHint"><?php echo $valorContrato('habitacional'); ?></textarea>
  <div id="crearHabitacionalHint" class="form-text ag-field-hint">Ejemplo: HABITACIONAL. COLINDA AL NORTE CON LOTE 12, AL SUR CON CALLE 5.</div>
</div>

<div class="col-md-6">
  <label class="form-label ag-keep-label" for="crearEntrega">Fecha de la posesión</label>
  <input type="date" class="form-control form-control-sm" name="entrega_posecion" id="crearEntrega" required value="<?php echo $valorContrato('entrega_posecion_date'); ?>" data-requirement="Fecha en que se entrega la posesión al cliente." aria-describedby="crearEntregaHint">
  <div id="crearEntregaHint" class="form-text ag-field-hint">Formato DD-MM-AAAA. Usado para redactar la cláusula de entrega.</div>
  <input type="hidden" name="entrega_posecion_texto" id="crearEntregaTexto" value="<?php echo $valorContrato('entrega_posecion'); ?>">
</div>

<div class="col-md-6">
  <label class="form-label ag-keep-label" for="crearClausulaPosecion">Cláusula "C" Posesión</label>
  <textarea class="form-control form-control-sm text-uppercase" name="clausula_c_posecion" id="crearClausulaPosecion" rows="4" data-requirement="Describe los términos específicos de la cláusula 'C' de posesión." aria-describedby="crearClausulaPosecionHint"><?php echo $valorContrato('clausula_c_posecion'); ?></textarea>
  <div id="crearClausulaPosecionHint" class="form-text ag-field-hint">Este texto se insertará en la cláusula "C" del contrato y estará disponible como placeholder en el documento.</div>
</div>

<div class="col-md-6">
  <label class="form-label ag-keep-label" for="crearFechaFirma">Fecha de firma del contrato</label>
  <input type="date" class="form-control form-control-sm" name="fecha_firma" id="crearFechaFirma" value="<?php echo $valorContrato('fecha_firma'); ?>" data-requirement="Fecha en que ambas partes firman el contrato." aria-describedby="crearFechaFirmaHint">
  <div id="crearFechaFirmaHint" class="form-text ag-field-hint">Si difiere de la fecha del contrato, especifíquela para reflejarla en el documento.</div>
  <input type="hidden" name="fecha_firma_texto" id="crearFechaFirmaTexto" value="<?php echo $valorContrato('fecha_firma_texto'); ?>">
</div>

<!-- Rango de pago (inicio y fin) -->
<div class="col-md-6">
  <fieldset class="border-0 p-0 m-0">
    <legend class="col-form-label pt-0 form-label ag-keep-label">Plazo del financiamiento</legend>
    <div class="row g-2 align-items-end">
      <div class="col-sm-6">
        <label class="form-label ag-keep-label" for="rangoPagoInicio">Fecha inicial</label>
        <input type="date" class="form-control form-control-sm" name="rango_pago_inicio" id="rangoPagoInicio" required value="<?php echo $valorContrato('rango_pago_inicio_date'); ?>" data-requirement="Fecha inicial del periodo de pagos (DD-MM-AAAA)." aria-describedby="rangoPagoHint">
        <input type="hidden" name="rango_pago_inicio_texto" id="rangoPagoInicioTexto" value="<?php echo $valorContrato('rango_pago_inicio'); ?>">
      </div>
      <div class="col-sm-6">
        <label class="form-label ag-keep-label" for="rangoPagoFin">Fecha final</label>
        <input type="date" class="form-control form-control-sm" name="rango_pago_fin" id="rangoPagoFin" required value="<?php echo $valorContrato('rango_pago_fin_date'); ?>" data-requirement="Fecha final del periodo de pagos (DD-MM-AAAA)." aria-describedby="rangoPagoHint">
        <input type="hidden" name="rango_pago_fin_texto" id="rangoPagoFinTexto" value="<?php echo $valorContrato('rango_pago_fin'); ?>">
      </div>
    </div>
    <div id="rangoPagoHint" class="form-text ag-field-hint mt-2">Define el intervalo completo de pagos programados (inicio y fin en formato DD-MM-AAAA).</div>
  </fieldset>
</div>
<div class="col-md-12">
  <label class="form-label ag-keep-label" for="crearClausulas">Cláusulas del financiamiento</label>
  <input type="text" class="form-control form-control-sm" name="financiamiento_clusulas" id="crearClausulas" min="1" placeholder="" required value="<?php echo $valorContrato('financiamiento_clusulas'); ?>" data-requirement="Describe el encabezado o nombre de las cláusulas financieras (ej. PLAN TRADICIONAL 12 MESES)." aria-describedby="crearClausulasHint">
  <div id="crearClausulasHint" class="form-text ag-field-hint">Este texto aparecerá en las cláusulas del contrato. Utiliza mayúsculas y sin abreviaturas ambiguas.</div>
</div>
<!-- Campos financieros existentes -->
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearMensualidades">Meses del financiamiento</label>
  <input type="number" class="form-control form-control-sm" name="mensualidades" id="crearMensualidades" min="1" placeholder="ej. 6" required value="<?php echo $valorContrato('mensualidades'); ?>" data-requirement="Número total de mensualidades pactadas (mínimo 1)." aria-describedby="crearMensualidadesHint">
  <div id="crearMensualidadesHint" class="form-text ag-field-hint">Ejemplo: 24 para un financiamiento de 2 años.</div>
</div>
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearRangoPago">Años del financiamiento</label>
  <input type="text" class="form-control form-control-sm" name="rango_pago" id="crearRangoPago" data-bs-toggle="tooltip" title="" placeholder="ej. 1 AÑO, 18 MESES" required value="<?php echo $valorContrato('rango_pago'); ?>" data-requirement="Descripción libre del plazo (ej. 1 AÑO, 18 MESES)." aria-describedby="crearRangoPagoHint">
  <div id="crearRangoPagoHint" class="form-text ag-field-hint">Usa mayúsculas y palabras completas para el plazo global.</div>
</div>
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearParcialidadesAnuales">Parcialidades anuales</label>
  <input type="text" placeholder="SIN PARCIALIDADES" class="form-control form-control-sm" name="parcialidades_anuales" id="crearParcialidadesAnuales" value="<?php echo $valorContrato('parcialidades_anuales'); ?>" data-requirement="Especifica las parcialidades extraordinarias (ej. 2 PAGOS DE $10,000) o escribe SIN PARCIALIDADES." aria-describedby="crearParcialidadesHint">
  <div id="crearParcialidadesHint" class="form-text ag-field-hint">Describe cantidad y periodicidad de pagos adicionales si existen.</div>
</div>

<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearMontoInmueble">Monto del precio del inmueble</label>
  <div class="input-group input-group-sm">
    <span class="input-group-text">$</span>
    <input type="number" step="0.01" class="form-control form-control-sm" name="monto_inmueble" id="crearMontoInmueble" required value="<?php echo $valorContrato('monto_inmueble'); ?>" data-requirement="Ingrese el precio total del inmueble con dos decimales." aria-describedby="crearMontoInmuebleHint">
    <input type="hidden" name="monto_inmueble_fixed" id="crearMontoInmuebleFixed" value="<?php echo $valorContrato('monto_inmueble_fixed'); ?>" data-numero-a-letras="moneda">
  </div>
  <div id="crearMontoInmuebleHint" class="form-text ag-field-hint">Capture el monto acordado en pesos mexicanos. Ejemplo: 350000.00.</div>
</div>

<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearEnganche">Enganche o pago inicial</label>
  <div class="input-group input-group-sm">
    <span class="input-group-text">$</span>
    <input type="number" step="0.01" class="form-control form-control-sm" name="enganche" id="crearEnganche" required value="<?php echo $valorContrato('enganche'); ?>" data-requirement="Monto pagado como enganche en pesos con dos decimales." aria-describedby="crearEngancheHint">
    <input type="hidden" name="enganche_fixed" id="crearEngancheFixed" value="<?php echo $valorContrato('enganche_fixed'); ?>" data-numero-a-letras="moneda">
  </div>
  <div id="crearEngancheHint" class="form-text ag-field-hint">Ejemplo: 50000.00. Debe ser menor o igual al precio del inmueble.</div>
</div>

<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearSaldoPago">Saldo de pago</label>
  <div class="input-group input-group-sm">
    <span class="input-group-text">$</span>
    <input type="number" step="0.01" class="form-control form-control-sm" name="saldo_pago" id="crearSaldoPago" readonly required value="<?php echo $valorContrato('saldo_pago'); ?>" data-requirement="Saldo restante después del enganche. Se calcula automáticamente." aria-describedby="crearSaldoPagoHint">
    <input type="hidden" name="saldo_pago_fixed" id="crearSaldoPagoFixed" value="<?php echo $valorContrato('saldo_pago_fixed'); ?>" data-numero-a-letras="moneda">
  </div>
  <div id="crearSaldoPagoHint" class="form-text ag-field-hint">Verifique que corresponda al precio menos el enganche. Valor en pesos.</div>
</div>

<!-- Nuevo campo: pago mensual -->
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearPagoMensual">Pago mensual</label>
  <div class="input-group input-group-sm">
    <span class="input-group-text">$</span>
    <input type="number" step="0.01" class="form-control form-control-sm" name="pago_mensual" id="crearPagoMensual" required value="<?php echo $valorContrato('pago_mensual'); ?>" data-requirement="Monto de cada pago mensual en pesos, con dos decimales." aria-describedby="crearPagoMensualHint">
    <input type="hidden" name="pago_mensual_fixed" id="crearPagoMensualFixed" value="<?php echo $valorContrato('pago_mensual_fixed'); ?>" data-numero-a-letras="moneda">
  </div>
  <div id="crearPagoMensualHint" class="form-text ag-field-hint">Ejemplo: 4500.00. Considera capital más intereses si aplica.</div>
</div>

<div class="col-md-3">
  <label class="form-label ag-keep-label" for="crearPenalizacion">Penalización 20%</label>
  <div class="input-group input-group-sm">
    <span class="input-group-text">$</span>
    <input type="number" step="0.01" class="form-control form-control-sm" name="penalizacion" id="crearPenalizacion" readonly required value="<?php echo $valorContrato('penalizacion'); ?>" data-requirement="Importe equivalente al 20% del saldo para penalizaciones. Se calcula automáticamente." aria-describedby="crearPenalizacionHint">
    <input type="hidden" name="penalizacion_fixed" id="crearPenalizacionFixed" value="<?php echo $valorContrato('penalizacion_fixed'); ?>" data-numero-a-letras="moneda">
  </div>
  <div id="crearPenalizacionHint" class="form-text ag-field-hint">Se genera con base en el saldo del contrato. Revise antes de continuar.</div>
</div>

<div class="col-md-6">
  <label class="form-label ag-keep-label" for="crearObservaciones">Observaciones</label>
  <textarea class="form-control form-control-sm" name="observaciones" id="crearObservaciones" rows="3" placeholder="Observaciones del contrato" data-requirement="Captura notas u observaciones relevantes para el contrato. Este campo es opcional y no se llena automáticamente." aria-describedby="crearObservacionesHint"><?php echo $valorContrato('observaciones'); ?></textarea>
  <div id="crearObservacionesHint" class="form-text ag-field-hint">Utiliza este espacio para registrar indicaciones o comentarios adicionales que deban acompañar al contrato.</div>
</div>
