<?php
$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/config/database.php';

$tips = [
    [
        'id' => 2,
        'titulo' => 'Comprueba la presión de tus neumáticos',
        'imagen' => 'https://picsum.photos/seed/tires/800/600',
        'texto' => '<p>Mantener la presión adecuada de tus neumáticos es uno de los mantenimientos más sencillos pero más importantes que puedes realizar en tu vehículo. Una presión incorrecta no solo afecta negativamente al consumo de combustible, haciendo que gastes más dinero del necesario, sino que también compromete seriamente tu seguridad en la carretera.</p>
                    <p>Cuando los neumáticos están desinflados, aumenta la superficie de contacto con el asfalto, lo que genera mayor fricción y calor, pudiendo provocar un reventón a altas velocidades. Además, el desgaste será irregular, acortando la vida útil de las gomas.</p>
                    <p>Recomendamos revisar la presión al menos una vez al mes y siempre antes de un viaje largo. Recuerda hacerlo cuando los neumáticos estén "fríos" (es decir, cuando no hayas circulado más de 2-3 kilómetros). Puedes encontrar la presión ideal recomendada por el fabricante en el manual del usuario o en una pegatina situada en el marco de la puerta del conductor.</p>'
    ],
    [
        'id' => 3,
        'titulo' => 'Cambio de aceite periódico',
        'imagen' => 'https://picsum.photos/seed/oil/800/600',
        'texto' => '<p>El aceite actúa como el "soporte vital" del motor de tu coche. Su función principal es lubricar las partes móviles para reducir la fricción, evitar el desgaste prematuro y ayudar a disipar el calor generado por la combustión. Con el tiempo y el uso, el aceite pierde sus propiedades y se llena de impurezas, lo que reduce su eficacia.</p>
                    <p>Si no cambias el aceite periódicamente, el motor trabajará a mayores temperaturas y la fricción extra desgastará los componentes internos de forma acelerada. A la larga, esto puede derivar en una avería grave y muy costosa de reparar.</p>
                    <p>Lo ideal es seguir las recomendaciones del fabricante, que suelen establecer intervalos de cambio entre los 10.000 y 15.000 kilómetros, o una vez al año si no alcanzas esa cifra. No escatimes en la calidad del aceite ni del filtro, ya que una pequeña inversión en mantenimiento preventivo te ahorrará muchos dolores de cabeza en el futuro.</p>'
    ],
    [
        'id' => 4,
        'titulo' => 'Revisión de frenos',
        'imagen' => 'https://picsum.photos/seed/brakes/800/600',
        'texto' => '<p>El sistema de frenado es, sin lugar a dudas, el elemento de seguridad activa más importante de tu vehículo. No esperes a escuchar ruidos extraños, como chirridos metálicos o un roce fuerte, para llevar tu coche al taller. Cuando estos sonidos aparecen, suele significar que las pastillas de freno están completamente desgastadas y el metal está rozando directamente contra el disco.</p>
                    <p>Además de las pastillas y los discos, es crucial revisar el líquido de frenos. Este fluido transmite la fuerza que ejerces sobre el pedal hasta las ruedas. Con el tiempo, el líquido absorbe humedad del ambiente, lo que reduce su punto de ebullición y disminuye considerablemente la eficacia del frenado, provocando que el pedal se sienta "esponjoso".</p>
                    <p>Acude a tu mecánico de confianza si notas vibraciones al frenar, si el coche se desvía hacia un lado, o si el recorrido del pedal es mayor de lo normal. Es recomendable hacer una inspección visual de las pastillas cada 15.000 kilómetros.</p>'
    ]
];

try {
    $stmt = $pdo->prepare("UPDATE consejos SET titulo = ?, imagen = ?, texto = ? WHERE idConsejo = ?");
    foreach ($tips as $tip) {
        $stmt->execute([$tip['titulo'], $tip['imagen'], $tip['texto'], $tip['id']]);
    }
    echo "Tips actualizados correctamente con contenido completo e imágenes.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
