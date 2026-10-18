<?php
require_once __DIR__ . '/../config/auth.php';
exigirRol(['admin', 'vendedor']);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/layout.php';

$idSede = sedeActualId($conexion);

$stmt = $conexion->prepare(
    "SELECT v.numero_venta, v.fecha_hora, v.total, v.estado, v.cliente_nombre
     FROM ventas v WHERE v.id_sede = ?
     ORDER BY v.id_venta DESC LIMIT 6"
);
$stmt->execute([$idSede]);
$ventas = $stmt->fetchAll();

$nombreSede = 'la tienda';
foreach (listarSedes($conexion) as $s) {
    if ((int)$s['id_sede'] === $idSede) $nombreSede = $s['nombre'];
}

layout_inicio($conexion, 'Caja registradora', 'caja', [
    'eyebrow' => 'Punto de venta',
    'descripcion' => 'Vendiendo desde ' . $nombreSede . '. El stock que ves y descuentas es el de esta tienda.',
]);
?>

<div id="caja-aviso" class="aviso toast" role="status"><span id="caja-aviso-texto"></span></div>

<div class="caja-rejilla">
    <div>
        <section class="panel">
            <h2 style="margin-bottom:14px">Agregar productos</h2>
            <form id="form-escaneo" class="caja-escaneo" autocomplete="off">
                <input id="codigo-producto" type="text"
                       placeholder="Escanea el código o escribe el nombre / marca…" autofocus required>
                <button id="btn-agregar" class="boton" type="submit"><?= icono('plus') ?> Agregar</button>
            </form>
            <div id="sugerencias" class="sugerencias" role="listbox"></div>
            <p class="nota-suave" style="margin-top:10px">Con lector de códigos, solo escanea. Si escribes un nombre, te mostramos coincidencias para elegir.</p>
        </section>

        <section class="panel">
            <h2 style="margin-bottom:10px">Carrito</h2>
            <div class="tabla-wrap">
                <table>
                    <thead>
                        <tr><th>Producto</th><th class="num">Precio</th><th>Cantidad</th><th class="num">Subtotal</th><th></th></tr>
                    </thead>
                    <tbody id="carrito-cuerpo"></tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <h2 style="margin-bottom:10px">Últimas ventas de <?= h($nombreSede) ?></h2>
            <div class="tabla-wrap">
                <table>
                    <thead><tr><th>Número</th><th>Cliente</th><th>Fecha</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php if (!$ventas): ?>
                        <tr><td colspan="4" class="vacio">Todavía no hay ventas en esta tienda.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td class="mono"><?= h($v['numero_venta']) ?></td>
                            <td><?= h($v['cliente_nombre'] ?: '—') ?></td>
                            <td><?= h(date('d/m H:i', strtotime($v['fecha_hora']))) ?></td>
                            <td class="num"><?= soles($v['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="panel caja-resumen">
        <h2 style="margin-bottom:10px">Resumen</h2>
        <div class="resumen-fila"><span>Productos distintos</span><strong id="cantidad-tipos">0</strong></div>
        <div class="resumen-fila"><span>Unidades</span><strong id="cantidad-unidades">0</strong></div>
        <div class="resumen-fila resumen-total"><span>Total</span><span id="total-venta">S/ 0.00</span></div>
        <button id="btn-cobrar" class="boton btn-cobrar" disabled>Cobrar y emitir boleta</button>
        <button id="btn-limpiar" class="boton sec btn-ancho" type="button">Vaciar carrito</button>
    </aside>
</div>

<div id="modal-cliente" class="modal" role="dialog" aria-modal="true" aria-labelledby="titulo-modal">
    <div class="modal-caja">
        <h2 id="titulo-modal">Datos del cliente</h2>
        <p class="nota-suave">Para la boleta. Si el DNI ya existe, usamos sus datos guardados.</p>
        <form id="form-cliente">
            <div class="form-rejilla" style="grid-template-columns:1fr">
                <div class="campo">
                    <label for="cliente-dni">DNI</label>
                    <input id="cliente-dni" type="text" inputmode="numeric" maxlength="8" pattern="[0-9]{8}" placeholder="8 dígitos" required>
                </div>
                <div class="campo">
                    <label for="cliente-nombre">Nombres y apellidos</label>
                    <input id="cliente-nombre" type="text" maxlength="150" placeholder="Obligatorio si es cliente nuevo">
                    <small id="cliente-ayuda">Escribe los 8 dígitos del DNI para buscar al cliente.</small>
                </div>
            </div>
            <div id="modal-error" class="aviso mal" style="display:none;margin-top:14px" role="alert"></div>
            <div class="modal-pie">
                <button type="button" id="btn-cancelar" class="boton sec">Cancelar</button>
                <button type="submit" id="btn-confirmar" class="boton">Confirmar cobro</button>
            </div>
        </form>
    </div>
</div>

<?php
$js = '<script>const csrfToken = ' . json_encode(csrfToken()) . ';</script>' . <<<'JS'
<script>
const carrito = new Map();
const $ = id => document.getElementById(id);
const inputCodigo = $('codigo-producto');
const sugerencias = $('sugerencias');
const modal = $('modal-cliente');
let ocupado = false;

const soles = v => 'S/ ' + Number(v).toFixed(2);

function foto(p, t) {
    if (p.imagen) {
        const i = document.createElement('img');
        i.src = p.imagen; i.alt = ''; i.className = 'foto-prod'; i.width = i.height = t;
        return i;
    }
    const s = document.createElement('span');
    s.className = 'avatar-prod';
    s.style.cssText = 'width:' + t + 'px;height:' + t + 'px;background:#e1f3e6;color:#0f6330';
    s.textContent = String(p.marca || p.nombre).replace(/[^\p{L}\p{N}]/gu, '').slice(0, 2).toUpperCase();
    return s;
}

function aviso(msg, tipo) {
    const a = $('caja-aviso');
    $('caja-aviso-texto').textContent = msg;
    a.className = 'aviso toast visible ' + (tipo === 'ok' ? 'ok' : 'mal');
    clearTimeout(aviso.t);
    aviso.t = setTimeout(() => { a.className = 'aviso toast'; }, 4500);
}

function agregar(p) {
    const id = Number(p.id);
    const actual = carrito.get(id);
    const nueva = (actual ? actual.cantidad : 0) + 1;
    if (nueva > Number(p.stock)) {
        aviso('Solo hay ' + p.stock + ' unidad(es) de ' + p.nombre + ' en esta tienda.', 'error');
        return;
    }
    carrito.set(id, {
        id: id, codigo: String(p.codigo || ''), nombre: String(p.nombre),
        marca: p.marca ? String(p.marca) : '', imagen: p.imagen || null, precio: Number(p.precio),
        stock: Number(p.stock), cantidad: nueva
    });
    dibujar();
    aviso(p.nombre + ' agregado.', 'ok');
}

async function buscar(texto) {
    texto = texto.trim();
    if (!texto || ocupado) return;
    ocupado = true;
    sugerencias.className = 'sugerencias';
    try {
        // 1) ¿Es un código exacto?
        let r = await fetch('buscar_producto.php?codigo=' + encodeURIComponent(texto), { headers: { Accept: 'application/json' } });
        let d = await r.json();
        if (r.ok && d.ok) { agregar(d.producto); inputCodigo.value = ''; return; }
        if (r.status === 409) { aviso(d.mensaje, 'error'); inputCodigo.select(); return; }

        // 2) Si no, buscar por nombre / marca
        r = await fetch('buscar_producto.php?q=' + encodeURIComponent(texto), { headers: { Accept: 'application/json' } });
        d = await r.json();
        if (!r.ok || !d.ok) throw new Error(d.mensaje || 'No se pudo buscar.');
        if (!d.resultados.length) { aviso('No encontramos productos con "' + texto + '".', 'error'); return; }
        mostrarSugerencias(d.resultados);
    } catch (e) {
        aviso(e.message || 'Error de conexión.', 'error');
    } finally {
        ocupado = false;
    }
}

function mostrarSugerencias(lista) {
    sugerencias.replaceChildren();
    lista.forEach(p => {
        const b = document.createElement('button');
        b.type = 'button';
        const centro = document.createElement('span');
        centro.className = 'crece';
        const n = document.createElement('strong'); n.textContent = p.nombre;
        const s = document.createElement('small');
        s.textContent = (p.marca ? p.marca + ' · ' : '') + (p.codigo || 'sin código') + ' · ' +
            (p.stock > 0 ? p.stock + ' en esta tienda' : 'sin stock aquí' + (p.otras ? ' (hay en ' + p.otras + ')' : ''));
        centro.append(n, document.createElement('br'), s);
        const precio = document.createElement('b'); precio.textContent = soles(p.precio);
        b.append(foto(p, 44), centro, precio);
        if (p.stock <= 0) b.style.opacity = '.55';
        b.addEventListener('click', () => {
            if (p.stock <= 0) { aviso('Sin stock en esta tienda.', 'error'); return; }
            agregar(p);
            sugerencias.className = 'sugerencias';
            inputCodigo.value = '';
            inputCodigo.focus();
        });
        sugerencias.appendChild(b);
    });
    sugerencias.className = 'sugerencias visible';
}

$('form-escaneo').addEventListener('submit', e => { e.preventDefault(); buscar(inputCodigo.value); });

function dibujar() {
    const cuerpo = $('carrito-cuerpo');
    cuerpo.replaceChildren();

    if (carrito.size === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 5; td.className = 'vacio';
        td.textContent = 'Tu carrito está vacío. Escanea un producto para empezar.';
        tr.appendChild(td); cuerpo.appendChild(tr);
    }

    carrito.forEach((p, id) => {
        const tr = document.createElement('tr');

        const t1 = document.createElement('td');
        const n = document.createElement('strong'); n.textContent = p.nombre;
        const c = document.createElement('small');
        c.textContent = (p.marca ? p.marca + ' · ' : '') + (p.codigo || '') + ' · quedan ' + p.stock;
        const tx = document.createElement('div'); tx.append(n, document.createElement('br'), c);
        const w = document.createElement('div'); w.className = 'celda-producto'; w.append(foto(p, 44), tx); t1.appendChild(w);

        const t2 = document.createElement('td'); t2.className = 'num'; t2.textContent = soles(p.precio);

        const t3 = document.createElement('td');
        const ctl = document.createElement('div'); ctl.className = 'cantidad-ctl';
        const menos = document.createElement('button'); menos.type = 'button'; menos.textContent = '−';
        const inp = document.createElement('input'); inp.type = 'number'; inp.min = '1'; inp.max = String(p.stock); inp.value = String(p.cantidad);
        inp.setAttribute('aria-label', 'Cantidad de ' + p.nombre);
        const mas = document.createElement('button'); mas.type = 'button'; mas.textContent = '+';
        const fijar = q => {
            q = Number.parseInt(q, 10);
            if (!Number.isInteger(q) || q < 1) q = 1;
            if (q > p.stock) { q = p.stock; aviso('Stock máximo en esta tienda: ' + p.stock, 'error'); }
            p.cantidad = q; dibujar();
        };
        menos.addEventListener('click', () => fijar(p.cantidad - 1));
        mas.addEventListener('click', () => fijar(p.cantidad + 1));
        inp.addEventListener('change', () => fijar(inp.value));
        ctl.append(menos, inp, mas); t3.appendChild(ctl);

        const t4 = document.createElement('td'); t4.className = 'num'; t4.textContent = soles(p.precio * p.cantidad);

        const t5 = document.createElement('td');
        const q = document.createElement('button'); q.type = 'button'; q.className = 'boton peligro chico'; q.textContent = 'Quitar';
        q.addEventListener('click', () => { carrito.delete(id); dibujar(); inputCodigo.focus(); });
        t5.appendChild(q);

        tr.append(t1, t2, t3, t4, t5);
        cuerpo.appendChild(tr);
    });

    let unidades = 0, total = 0;
    carrito.forEach(p => { unidades += p.cantidad; total += p.precio * p.cantidad; });
    $('cantidad-tipos').textContent = carrito.size;
    $('cantidad-unidades').textContent = unidades;
    $('total-venta').textContent = soles(total);
    $('btn-cobrar').disabled = carrito.size === 0;
}

$('btn-limpiar').addEventListener('click', () => {
    if (carrito.size && confirm('¿Quitar todos los productos del carrito?')) { carrito.clear(); dibujar(); inputCodigo.focus(); }
});

$('btn-cobrar').addEventListener('click', () => {
    if (!carrito.size) return;
    $('modal-error').style.display = 'none';
    modal.classList.add('abierto');
    $('cliente-dni').focus();
});
$('btn-cancelar').addEventListener('click', () => { modal.classList.remove('abierto'); inputCodigo.focus(); });

// Buscar cliente por DNI
const inDni = $('cliente-dni'), inNom = $('cliente-nombre'), ayuda = $('cliente-ayuda');
let ultimoDni = '', numBusqueda = 0;
inDni.addEventListener('input', async () => {
    const dni = inDni.value.trim();
    const mi = ++numBusqueda;
    if (!/^\d{8}$/.test(dni)) {
        ultimoDni = ''; inNom.value = ''; inNom.readOnly = false;
        ayuda.textContent = 'Escribe los 8 dígitos del DNI para buscar al cliente.';
        return;
    }
    if (dni === ultimoDni) return;
    ultimoDni = dni; inNom.value = ''; inNom.readOnly = true; ayuda.textContent = 'Buscando…';
    try {
        const r = await fetch('buscar_cliente.php?dni=' + encodeURIComponent(dni), { headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (mi !== numBusqueda) return;
        if (!r.ok || !d.ok) throw new Error(d.mensaje || 'No se pudo buscar.');
        if (d.encontrado) {
            inNom.value = d.nombre; inNom.readOnly = true;
            ayuda.textContent = '✓ Cliente registrado: usaremos sus datos.';
        } else {
            inNom.readOnly = false;
            ayuda.textContent = 'Cliente nuevo: escribe sus nombres y apellidos para registrarlo.';
            inNom.focus();
        }
    } catch (e) {
        if (mi !== numBusqueda) return;
        inNom.readOnly = false;
        ayuda.textContent = 'No se pudo consultar el DNI. Puedes escribir el nombre manualmente.';
    }
});

$('form-cliente').addEventListener('submit', async e => {
    e.preventDefault();
    const dni = inDni.value.trim(), nombre = inNom.value.trim();
    const err = $('modal-error'), btn = $('btn-confirmar');
    if (!/^\d{8}$/.test(dni)) { err.textContent = 'El DNI debe tener 8 dígitos.'; err.style.display = 'flex'; return; }
    btn.disabled = true; btn.textContent = 'Procesando…';
    try {
        const r = await fetch('procesar_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken, cliente_dni: dni, cliente_nombre: nombre,
                carrito: Array.from(carrito.values()).map(p => ({ id: p.id, cantidad: p.cantidad }))
            })
        });
        const d = await r.json();
        if (!r.ok || !d.ok) throw new Error(d.mensaje || 'No se pudo procesar el cobro.');
        window.location.href = d.boleta_url;
    } catch (ex) {
        err.textContent = ex.message || 'Error de conexión.'; err.style.display = 'flex';
        btn.disabled = false; btn.textContent = 'Confirmar cobro';
    }
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.classList.remove('abierto'); });
dibujar();
inputCodigo.focus();
</script>
JS;
layout_fin($js);
