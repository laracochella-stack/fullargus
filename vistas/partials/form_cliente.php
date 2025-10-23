<!-- Sección de datos del cliente -->
<!-- CSS intl-tel-input (si ya lo cargas globalmente, puedes quitar esta línea) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css"/>

<?php
$clientePrefill = $clientePrefill ?? [];
$valorCliente = static function (string $campo) use ($clientePrefill): string {
    return htmlspecialchars((string)($clientePrefill[$campo] ?? ''), ENT_QUOTES);
};
$telefonoPrefill = htmlspecialchars((string)($clientePrefill['telefono_cliente_visible'] ?? $clientePrefill['cliente_telefono'] ?? ''), ENT_QUOTES);
$generoPrefill = strtoupper(trim((string)($clientePrefill['cliente_genero'] ?? '')));
$identificacionPrefill = strtoupper(trim((string)($clientePrefill['cliente_identificacion'] ?? '')));
$diceSerPrefillRaw = (string)($clientePrefill['dice_ser'] ?? '');
if ($diceSerPrefillRaw === '' && !empty($clientePrefill['parentesco_beneficiario'])) {
    $diceSerPrefillRaw = (string)$clientePrefill['parentesco_beneficiario'];
}
if ($diceSerPrefillRaw !== '') {
    $diceSerPrefillRaw = function_exists('mb_strtoupper')
        ? mb_strtoupper($diceSerPrefillRaw, 'UTF-8')
        : strtoupper($diceSerPrefillRaw);
}
$diceSerPrefill = htmlspecialchars($diceSerPrefillRaw, ENT_QUOTES);
?>

<div class="col-md-9">
  <label class="form-label ag-keep-label" for="clienteNombre">Nombre completo</label>
  <input type="text" id="clienteNombre" class="form-control form-control-sm text-uppercase" name="cliente_nombre" placeholder="ej. JUAN PÉREZ" required value="<?php echo $valorCliente('cliente_nombre'); ?>" data-requirement="Escribe el nombre(s) y apellidos tal como aparecen en la identificación oficial, en mayúsculas." aria-describedby="clienteNombreHint">
  <div id="clienteNombreHint" class="form-text ag-field-hint">Incluye nombre(s) y apellidos completos en mayúsculas, sin abreviaturas.</div>
</div>
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="clienteNacionalidad">Nacionalidad</label>
  <!-- Para mantener compatibilidad con la base de datos, almacenamos el nombre de la nacionalidad en lugar del identificador -->
  <select class="form-select form-select-sm" id="clienteNacionalidad" name="cliente_nacionalidad" required data-requirement="Selecciona la nacionalidad que corresponde a la identificación oficial del cliente." aria-describedby="clienteNacionalidadHint">
    <option value="">Seleccione</option>
    <?php foreach ($nacionalidades as $nac) : ?>
      <option value="<?php echo htmlspecialchars($nac['nombre'], ENT_QUOTES); ?>" <?php echo ($clientePrefill['cliente_nacionalidad'] ?? '') === $nac['nombre'] ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars($nac['nombre']); ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div id="clienteNacionalidadHint" class="form-text ag-field-hint">Elige la nacionalidad exactamente como aparece en la documentación oficial.</div>
</div>
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="clienteGenero">Género</label>
  <select class="form-select form-select-sm" id="clienteGenero" name="cliente_genero" required data-requirement="Selecciona el género con base en el tratamiento correspondiente del cliente." aria-describedby="clienteGeneroHint" placeholder="Seleccione género" data-placeholder="Seleccione género">
    <option value="">Seleccione género</option>
    <option value="AL C." <?php echo in_array($generoPrefill, ['AL C.', 'ALC'], true) ? 'selected' : ''; ?>>Masculino (AL C.)</option>
    <option value="A LA C." <?php echo in_array($generoPrefill, ['A LA C.', 'LA C.', 'ALAC', 'LAC'], true) ? 'selected' : ''; ?>>Femenino (A LA C.)</option>
  </select>
  <div id="clienteGeneroHint" class="form-text ag-field-hint">Elige el tratamiento adecuado: AL C. para masculino o A LA C. para femenino.</div>
</div>
<div class="col-md-4">
  <label class="form-label ag-keep-label" for="clienteFechaNacimiento">Fecha de nacimiento</label>
  <input type="date" class="form-control form-control-sm" name="cliente_fecha_nacimiento" id="clienteFechaNacimiento" required value="<?php echo $valorCliente('cliente_fecha_nacimiento'); ?>" data-requirement="Indique la fecha en formato DD-MM-AAAA. El sistema la ajustará automáticamente." aria-describedby="clienteFechaNacimientoHint">
  <div id="clienteFechaNacimientoHint" class="form-text ag-field-hint">Capture la fecha en formato DD-MM-AAAA; se almacenará en la misma zona horaria y se validará que la persona sea mayor de edad.</div>
  <!-- Campo oculto para almacenar la edad calculada del cliente -->
  <input type="hidden" name="cliente_edad" id="clienteEdad">
  <input type="hidden" name="cliente_fecha_nacimiento_texto" id="clienteFechaNacimientoTexto">
</div>
<div class="col-md-4">
  <label class="form-label ag-keep-label" for="clienteRfc">RFC</label>
  <input type="text" id="clienteRfc" class="form-control form-control-sm text-uppercase" name="cliente_rfc" required placeholder="ej. XEXX010101000" pattern="^[A-Za-zÑ&]{3,4}\d{6}[A-Za-z0-9]{3}$" value="<?php echo $valorCliente('cliente_rfc'); ?>" data-requirement="13 caracteres: 3 o 4 letras (puede incluir &), 6 dígitos de fecha y 3 caracteres alfanuméricos." aria-describedby="clienteRfcHint">
  <div id="clienteRfcHint" class="form-text ag-field-hint">Ejemplo: ABCD001231XYZ. Utiliza mayúsculas y captura sin guiones.</div>
</div>
<div class="col-md-4">
  <label class="form-label ag-keep-label" for="clienteCurp">CURP</label>
  <input type="text" id="clienteCurp" class="form-control form-control-sm text-uppercase" name="cliente_curp" required placeholder="ej. XEXX010101HNEXXXA4" value="<?php echo $valorCliente('cliente_curp'); ?>" pattern="^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]{2}$" data-requirement="18 caracteres: 4 letras, fecha de nacimiento, sexo y entidad en el formato oficial de la CURP." aria-describedby="clienteCurpHint">
  <div id="clienteCurpHint" class="form-text ag-field-hint">Debe coincidir con la CURP oficial (18 caracteres alfanuméricos en mayúsculas).</div>
</div>
<div class="col-md-4">
  <label class="form-label ag-keep-label" for="clienteIdentificacion">Identificación</label>
  <select class="form-select form-select-sm" id="clienteIdentificacion" name="cliente_identificacion" required data-requirement="Selecciona el documento oficial con el que se identificó el cliente." aria-describedby="clienteIdentificacionHint">
    <option value="">Seleccione</option>
    <option value="INE" <?php echo $identificacionPrefill === 'INE' ? 'selected' : ''; ?>>INE</option>
    <option value="PASAPORTE" <?php echo $identificacionPrefill === 'PASAPORTE' ? 'selected' : ''; ?>>PASAPORTE</option>
    <option value="CEDULA PROFESIONAL" <?php echo $identificacionPrefill === 'CEDULA PROFESIONAL' ? 'selected' : ''; ?>>CEDULA PROFESIONAL</option>
  </select>
  <div id="clienteIdentificacionHint" class="form-text ag-field-hint">Elige el tipo de documento oficial presentado (INE, PASAPORTE o CEDULA PROFESIONAL).</div>
</div>
<div class="col-md-4">
  <label class="form-label ag-keep-label" for="clienteIne">INE (IDMEX)</label>
  <input type="text" id="clienteIne" class="form-control form-control-sm text-uppercase number" name="cliente_ine" pattern="[0-9]*" maxlength="13" required placeholder="13 Dígitos al reverso de la INE" value="<?php echo $valorCliente('cliente_ine'); ?>" data-requirement="Captura los 13 dígitos numéricos del identificador IDMEX tal como aparece en la credencial." aria-describedby="clienteIneHint">
  <div id="clienteIneHint" class="form-text ag-field-hint">Ingresa únicamente números, sin espacios ni guiones.</div>
</div>
<div class="col-md-4">
  <label class="form-label ag-keep-label" for="clienteEstadoCivil">Estado civil y régimen matrimonial</label>
  <input type="text" id="clienteEstadoCivil" class="form-control form-control-sm text-uppercase" name="cliente_estado_civil" required placeholder="ej. SOLTERO" value="<?php echo $valorCliente('cliente_estado_civil'); ?>" data-requirement="Describe el estado civil y el régimen matrimonial (si aplica) en mayúsculas." aria-describedby="clienteEstadoCivilHint">
  <div id="clienteEstadoCivilHint" class="form-text ag-field-hint">Ejemplo: CASADO · SOCIEDAD CONYUGAL o SOLTERO.</div>
</div>
<div class="col-md-4">
  <label class="form-label ag-keep-label" for="clienteOcupacion">Ocupación</label>
  <input type="text" id="clienteOcupacion" class="form-control form-control-sm text-uppercase" name="cliente_ocupacion" required placeholder="ej. INGENIERO" value="<?php echo $valorCliente('cliente_ocupacion'); ?>" data-requirement="Indica la ocupación o profesión principal, en mayúsculas y sin abreviaturas." aria-describedby="clienteOcupacionHint">
  <div id="clienteOcupacionHint" class="form-text ag-field-hint">Registra la ocupación que aparece en documentos oficiales o comprobantes laborales.</div>
</div>
<div class="col-md-6">
  <label class="form-label ag-keep-label" for="clienteEmail">Correo electrónico</label>
  <input type="email" id="clienteEmail" class="form-control form-control-sm" name="cliente_email" placeholder="ej. micorreo@dominio.com" required value="<?php echo $valorCliente('cliente_email'); ?>" data-requirement="Ingresa un correo válido que se utilice para notificaciones." aria-describedby="clienteEmailHint">
  <div id="clienteEmailHint" class="form-text ag-field-hint">Ejemplo: usuario@dominio.com. Verifica que esté activo para recibir avisos.</div>
</div>
<div class="col-md-3">
  <label class="form-label ag-keep-label" for="telefono_cliente">Teléfono</label>
  <input type="tel" class="form-control form-control-sm" id="telefono_cliente" data-intl-hidden="#cliente_telefono" required value="<?php echo $telefonoPrefill; ?>" data-requirement="Selecciona la lada y captura un número de 10 dígitos como mínimo." aria-describedby="clienteTelefonoHint">
  <div class="invalid-feedback">Ingrese un número válido.</div>
  <div id="clienteTelefonoHint" class="form-text ag-field-hint">Incluye la clave lada del país. El número final se guardará con prefijo internacional.</div>
</div>
<!-- Campo oculto donde se guardará el número final con código de país -->
<input type="hidden" name="cliente_telefono" id="cliente_telefono" value="<?php echo htmlspecialchars((string)($clientePrefill['cliente_telefono'] ?? ''), ENT_QUOTES); ?>">

<div class="col-md-9">
  <label class="form-label ag-keep-label" for="clienteDomicilio">Domicilio</label>
  <input type="text" id="clienteDomicilio" class="form-control form-control-sm text-uppercase" name="cliente_domicilio" required placeholder="ej. CALLE # COL" value="<?php echo $valorCliente('cliente_domicilio'); ?>" data-requirement="Captura calle, número, colonia, ciudad y estado en mayúsculas." aria-describedby="clienteDomicilioHint">
  <div id="clienteDomicilioHint" class="form-text ag-field-hint">Incluye calle, número exterior/interior, colonia, municipio y estado.</div>
</div>

<div class="col-md-6 mb-3">
  <label class="form-label ag-keep-label" for="clienteBeneficiario">Beneficiario</label>
  <input type="text" id="clienteBeneficiario" class="form-control form-control-sm text-uppercase" name="cliente_beneficiario" required placeholder="ej. NOMBRE (PARENTESCO)" value="<?php echo $valorCliente('cliente_beneficiario'); ?>" data-requirement="Registra el nombre completo del beneficiario y su parentesco en mayúsculas." aria-describedby="clienteBeneficiarioHint">
  <div id="clienteBeneficiarioHint" class="form-text ag-field-hint">Ejemplo: ANA LÓPEZ (HERMANA). Utiliza mayúsculas y especifica parentesco.</div>
</div>
<div class="col-md-6 mb-3">
  <label class="form-label ag-keep-label" for="clienteDiceSer">Dice ser</label>
  <input type="text" id="clienteDiceSer" class="form-control form-control-sm text-uppercase" name="dice_ser" placeholder="ej. DICE SER COMERCIANTE" value="<?php echo $diceSerPrefill; ?>" data-requirement="Describe la frase 'DICE SER' que se incluirá en el pagaré (ej. DICE SER COMERCIANTE)." aria-describedby="clienteDiceSerHint" required>
  <div id="clienteDiceSerHint" class="form-text ag-field-hint">Si la solicitud original registró un parentesco, se utilizará como sugerencia para este campo. Se guardará en mayúsculas tal como aparecerá en el pagaré asociado al contrato.</div>
</div>


