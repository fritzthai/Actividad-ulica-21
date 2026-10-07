'use strict';

const formulario = document.querySelector('#formulario');
const listado = document.querySelector('#productos');
const mensaje = document.querySelector('#mensaje');
const guardar = document.querySelector('#guardar');
let token = '';

async function solicitar(opciones = {}) {
  const respuesta = await fetch('productos.php', opciones);
  const datos = await respuesta.json();
  if (!respuesta.ok) throw new Error(datos.error || 'No se pudo completar la operación.');
  return datos;
}

async function cargarProductos() {
  const datos = await solicitar();
  token = datos.token;
  listado.replaceChildren();
  for (const producto of datos.productos) {
    const fila = document.createElement('tr');
    for (const valor of [producto.id, producto.nombre, producto.categoria,
      Number(producto.precio).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      producto.stock]) {
      const celda = document.createElement('td');
      celda.textContent = String(valor);
      fila.append(celda);
    }
    listado.append(fila);
  }
  guardar.disabled = false;
}

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  guardar.disabled = true;
  mensaje.textContent = 'Guardando…';
  let insertado = false;
  try {
    const datos = Object.fromEntries(new FormData(formulario));
    const respuesta = await solicitar({
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify(datos)
    });
    insertado = true;
    formulario.reset();
    await cargarProductos();
    mensaje.textContent = respuesta.mensaje;
  } catch (error) {
    mensaje.textContent = insertado
      ? 'Producto guardado, pero no se pudo actualizar el listado. Recargá la página.'
      : error.message;
  } finally {
    guardar.disabled = !token;
  }
});

document.addEventListener('DOMContentLoaded', async () => {
  try {
    await cargarProductos();
  } catch (error) {
    mensaje.textContent = error.message;
  }
});
