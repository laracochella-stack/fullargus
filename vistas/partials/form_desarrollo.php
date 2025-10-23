<!-- Sección de datos del desarrollo -->
<?php
$desarrolloPrefill = $desarrolloPrefill ?? [];
$desarrolloSeleccionadoId = (int)($desarrolloPrefill['desarrollo_id'] ?? 0);
$superficiePrefill = htmlspecialchars((string)($desarrolloPrefill['contrato_superficie'] ?? ''), ENT_QUOTES);
$tipoIdPrefill = htmlspecialchars((string)($desarrolloPrefill['tipo_contrato_id'] ?? ''), ENT_QUOTES);
$tipoNombrePrefill = htmlspecialchars((string)($desarrolloPrefill['tipo_contrato_nombre'] ?? ''), ENT_QUOTES);
$superficieFixedPrefill = htmlspecialchars((string)($desarrolloPrefill['superficie_fixed'] ?? ''), ENT_QUOTES);
?>

<div class="col-md-6">
  <label class="form-label" for="selectDesarrolloCrear">Desarrollo</label>
  <select class="form-select form-select-sm" name="desarrollo_id" id="selectDesarrolloCrear" required data-requirement="Selecciona el desarrollo autorizado para este contrato." aria-describedby="crearDesarrolloHint">
    <option value="">Seleccione un desarrollo</option>
    <?php foreach ($desarrollos as $des) : ?>
      <option value="<?php echo $des['id']; ?>"
              data-superficie="<?php echo htmlspecialchars($des['superficie'], ENT_QUOTES); ?>"
              data-tipo-id="<?php echo htmlspecialchars($des['tipo_contrato'], ENT_QUOTES); ?>"
              data-tipo-nombre="<?php echo htmlspecialchars($tiposContrato[$des['tipo_contrato']] ?? $des['tipo_contrato'], ENT_QUOTES); ?>"
              data-lotes="<?php echo htmlspecialchars($des['lotes_disponibles'] ?? '', ENT_QUOTES); ?>"
              <?php echo $desarrolloSeleccionadoId === (int)$des['id'] ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($des['nombre']); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div id="crearDesarrolloHint" class="form-text ag-field-hint">Elige el desarrollo registrado en el sistema. Se cargará automáticamente su superficie y plantilla.</div>
</div>
<div class="col-md-6">
  <label class="form-label" for="crearSuperficie">Superficie</label>
  <input type="text" class="form-control form-control-sm number_dec" id="crearSuperficie" name="contrato_superficie" placeholder="TAMAÑO DE LA FRACCIÓN" required value="<?php echo $superficiePrefill; ?>" data-requirement="Ingresa la superficie numérica (puede incluir decimales) en metros cuadrados." aria-describedby="crearSuperficieHint">
  <div id="crearSuperficieHint" class="form-text ag-field-hint">Ejemplo: 120.50. El valor se expresará en metros cuadrados en el contrato.</div>
  <!-- Campo oculto para almacenar la superficie convertida a letras -->
  <input type="hidden" name="superficie_fixed" id="crearSuperficieFixed" value="<?php echo $superficieFixedPrefill; ?>" data-numero-a-letras="superficie">
</div>
<div class="col-md-6">
  <label class="form-label" for="crearTipoNombre">Plantilla del contrato</label>
  <input type="hidden" name="tipo_contrato" id="crearTipoId" value="<?php echo $tipoIdPrefill; ?>">
  <input type="text" class="form-control form-control-sm" id="crearTipoNombre" readonly value="<?php echo $tipoNombrePrefill; ?>" aria-describedby="crearTipoNombreHint">
  <div id="crearTipoNombreHint" class="form-text ag-field-hint">Se selecciona automáticamente según el desarrollo. Confirme que coincida con el tipo de contrato esperado.</div>
</div>
