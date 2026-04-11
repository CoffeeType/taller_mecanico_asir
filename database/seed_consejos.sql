-- Seed for table `consejos`
-- Generated: 2026-03-10T10:09:59.526Z

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `consejos`;

INSERT INTO `consejos` (`idConsejo`, `titulo`, `imagen`, `texto`, `fecha`, `idUser`) VALUES
  (1, '¿Cuándo cambiar el aceite?', 'img/tip_oil.png', '<h2>La importancia del cambio de aceite</h2>
<p>El aceite es la sangre de su motor. Lubrica las partes móviles, reduce la fricción, disipa el calor y mantiene el motor limpio. Con el tiempo, el aceite se degrada y pierde sus propiedades, lo que puede llevar a un desgaste prematuro del motor.</p>

<h3>¿Cada cuánto tiempo?</h3>
<p>La frecuencia del cambio de aceite depende de varios factores:</p>
<ul>
    <li><strong>Recomendación del fabricante:</strong> Es la regla de oro. Consulte el manual de su vehículo. Generalmente oscila entre 15.000 y 30.000 km.</li>
    <li><strong>Tipo de aceite:</strong> Los aceites sintéticos modernos duran más que los minerales convencionales.</li>
    <li><strong>Condiciones de conducción:</strong> Si conduce principalmente en ciudad (muchas paradas y arranques), transporta cargas pesadas o conduce en climas extremos, es posible que necesite cambiarlo con más frecuencia.</li>
    <li><strong>Antigüedad del vehículo:</strong> Los coches más antiguos pueden requerir cambios más frecuentes.</li>
</ul>

<h3>Señales de que necesitas un cambio</h3>
<ul>
    <li>Aceite oscuro y sucio en la varilla de medición.</li>
    <li>Ruido del motor más fuerte de lo habitual.</li>
    <li>Luz de advertencia de "Check Engine" o de presión de aceite encendida.</li>
    <li>Humo de escape oscuro.</li>
</ul>

<p>No espere a que sea demasiado tarde. Un cambio de aceite es una inversión pequeña que puede ahorrarle reparaciones muy costosas en el futuro.</p>', '2025-12-12 23:00:00', 1),
  (2, 'Prepara tu coche para la ITV', 'img/tip_filter.png', '<h2>Pasa la ITV a la primera</h2>
<p>La Inspección Técnica de Vehículos (ITV) es un trámite obligatorio que garantiza que los vehículos en circulación cumplen con las normas de seguridad y emisiones. Suspenderla por un fallo leve puede ser una molestia, pero un fallo grave inmovilizará su vehículo.</p>

<h3>Nuestra Checklist Pre-ITV</h3>
<p>Antes de ir a la estación de inspección, revise lo siguiente:</p>
<ol>
    <li><strong>Luces:</strong> Compruebe todas las luces exteriores e interiores. Luces de cruce, largas, intermitentes, freno, marcha atrás y antiniebla. También la luz de la matrícula.</li>
    <li><strong>Neumáticos:</strong> Verifique la profundidad del dibujo (mínimo legal 1.6 mm) y que no tengan cortes ni deformaciones. Compruebe también la presión.</li>
    <li><strong>Frenos:</strong> Si nota vibraciones al frenar o el coche se desvía, acuda al taller. El freno de mano debe ser capaz de sujetar el coche en pendiente.</li>
    <li><strong>Niveles:</strong> Aceite, refrigerante, líquido de frenos y limpiaparabrisas.</li>
    <li><strong>Limpiaparabrisas:</strong> Las escobillas deben limpiar bien y no dejar marcas. El depósito de agua debe estar lleno.</li>
    <li><strong>Cinturones de seguridad:</strong> Deben anclar correctamente y recogerse con fuerza.</li>
    <li><strong>Testigos del cuadro:</strong> Ningún testigo de avería (especialmente airbag o motor) debe quedarse encendido tras el arranque.</li>
</ol>

<p>Si tiene dudas, en nuestro taller ofrecemos un servicio de revisión Pre-ITV donde nos encargamos de todo.</p>', '2025-12-12 23:00:00', 1),
  (3, '5 formas de ahorrar combustible', 'img/tip_tires.png', '<h2>Conducción eficiente: Cuide su bolsillo y el planeta</h2>
<p>El precio del combustible es una preocupación para todos los conductores. Afortunadamente, pequeños cambios en sus hábitos de conducción pueden suponer un gran ahorro a final de mes.</p>

<h3>5 Consejos Prácticos</h3>
<ol>
    <li><strong>Conduzca con suavidad:</strong> Evite acelerones y frenazos bruscos. Una conducción anticipativa y fluida puede reducir el consumo hasta un 20%.</li>
    <li><strong>Mantenga la velocidad constante:</strong> En carretera, el uso del control de crucero ayuda a mantener una velocidad estable, lo que es más eficiente.</li>
    <li><strong>Revise la presión de los neumáticos:</strong> Los neumáticos con baja presión aumentan la resistencia a la rodadura y, por tanto, el consumo. Revíselos al menos una vez al mes.</li>
    <li><strong>Vigile la carga y la aerodinámica:</strong> No lleve peso innecesario en el maletero. Si no usa la baca o el cofre de techo, desmóntelos, ya que aumentan considerablemente la resistencia al aire.</li>
    <li><strong>Use el aire acondicionado con inteligencia:</strong> A bajas velocidades, es mejor abrir las ventanillas. A altas velocidades (carretera), la resistencia del aire con las ventanillas abiertas es peor que el consumo del aire acondicionado, así que úselo moderadamente.</li>
</ol>

<p>Además, un coche bien mantenido siempre consumirá menos. No se salte las revisiones periódicas.</p>', '2025-12-12 23:00:00', 1),
  (4, 'Inspección de Frenos', 'img/tip_brakes.png', '<h2>Tu seguridad es lo primero</h2><p>Revisa tus frenos cada 20,000 km. Pastillas desgastadas o discos rayados comprometen tu seguridad. Escucha chirridos y presta atención a vibraciones al frenar.</p><ul><li>Grosor mínimo: 3mm</li><li>Líquido de frenos: cada 2 años</li></ul>', '2025-12-12 23:00:00', 1),
  (5, 'Reemplazo de Filtro de Aire', 'img/tip_filter.png', '<h2>Respira mejor tu motor</h2><p>Un filtro sucio reduce potencia y aumenta consumo. Reemplázalo cada 15,000-30,000 km según uso.</p><ul><li>Mejora combustión</li><li>Bajo costo</li></ul>', '2025-12-12 23:00:00', 1),
  (6, 'Mantenimiento de Batería', 'img/tip_battery.png', '<h2>Evita quedarte tirado</h2><p>Batería dura 3-5 años. Limpia bornes de corrosión, verifica carga, y evita descargas profundas.</p><ul><li>Bornes limpios</li><li>Test de carga anual</li></ul>', '2025-12-12 23:00:00', 1);

SET FOREIGN_KEY_CHECKS = 1;
