USE trabajo_final_php;
SET NAMES utf8mb4;
DELETE FROM noticias;
SET @admin_id = (SELECT idUser FROM users_login WHERE rol = 'admin' LIMIT 1);
SET @admin_id = IFNULL(@admin_id, 1);
INSERT INTO noticias (idUser, titulo, texto, imagen, fecha, enlace) VALUES 
(@admin_id, 'Cazado el Mercedes-AMG CLA Shooting Brake eléctrico en pruebas, la aerodinámica activa marca su enfoque más radical', '
                            
                                
                            
                            Fotos espía Mercedes-AMG CLA S\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/mercedes-amg-cla-shooting-brake-2027-fotos-espia-2025111575-1765713317_1.jpg', '2025-12-15', 'https://www.motor.es/noticias/mercedes-amg-cla-shooting-brake-2027-pruebas-invierno-2025111575.html'),
(@admin_id, 'Más de 18.000 € de descuento y 510 km de autonomía para el Hyundai Kona con etiqueta CERO que desafía al KIA EV3', '
                            
                                
                            
                            El Hyundai Kona que desafía al\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/oferta-hyundai-kona-electrico-65-kwh-black-line-2025111581-1765715099_1.jpg', '2025-12-14', 'https://www.motor.es/noticias/oferta-hyundai-kona-electrico-65-kwh-black-line-2025111581.html'),
(@admin_id, 'Dos grandes problemas llevan a revisión a 5 millones de motores de gasolina, ¿estamos ante los límites de la combustión?', '
                            
                                
                            
                            Las leyes de la física y la in\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/motores-gasolina-revision-2025111580-1765707021_6.jpg', '2025-12-14', 'https://www.motor.es/noticias/motores-gasolina-revision-2025111580.html'),
(@admin_id, 'Este SUV híbrido japonés con fiabilidad Toyota mete presión al BMW X1 con 3.000 € de rebaja y un motor de 199 CV que solo gasta 5 L/100 km', '
                            
                                
                            
                            Con fiabilidad Toyota, un moto\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/oferta-lexus-ux-300h-urban-2025111579-1765711348_1.jpg', '2025-12-14', 'https://www.motor.es/noticias/oferta-lexus-ux-300h-urban-2025111579.html'),
(@admin_id, 'Herbert, el denominado ‘hater’ de Alonso, sí es un gran fan de Sainz: “Si le dieran un McLaren…”', '
                            
                                
                            
                            Carlos Sainz celebra su podio \n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/herbert-hater-alonso-fan-sainz-dieran-mclaren-2025111577-1765626867_1.jpg', '2025-12-14', 'https://www.motor.es/formula-1/herbert-hater-alonso-fan-sainz-dieran-mclaren-2025111577.html'),
(@admin_id, 'Ni sal ni químicos, Alemania usa agua de pepinillos para evitar el hielo en carreteras y aeropuertos', '
                            
                                
                            
                            Como aperitivo, es un manjar p\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/coches-2025111571-1765568261_1.jpg', '2025-12-14', 'https://www.motor.es/noticias/alemania-solucion-hielo-carreteras-2025111571.html'),
(@admin_id, 'Estos errores de mantenimiento del coche hacen rico a tu mecánico, te contamos cómo puedes evitarlos', '
                            
                                
                            
                            En muchas ocasiones acudimos a\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/errores-mantenimiento-hacen-rico-a-tu-mecanico-2025111560-1765533916_1.jpg', '2025-12-14', 'https://www.motor.es/practicos/errores-mantenimiento-hacen-rico-a-tu-mecanico-2025111560.html'),
(@admin_id, 'Es 1.300 € más barato que el Leapmotor T03 y no hay color, es mejor en todo y tiene una autonomía superior', '
                            
                                
                            
                            Con más de 11.600 € de rebaja \n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/oferta-dongfeng-box-2026-electrico-plus-2025111578-1765628173_1.jpg', '2025-12-13', 'https://www.motor.es/noticias/oferta-dongfeng-box-2026-electrico-plus-2025111578.html'),
(@admin_id, 'Mercedes va tarde, Nissan prepara el mayor salto en conducción autónoma que nadie veía venir', '
                            
                                
                            
                            La marca japonesa prueba un im\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/nissan-2025111573-1765561454_4.jpg', '2025-12-13', 'https://www.motor.es/noticias/nissan-propilot-2027-video-2025111573.html'),
(@admin_id, 'Más que recargar el coche: así es la red que cambia las reglas del juego (y sin pagar más)', '
                            
                                
                            
                            MOEVE cuenta con una red de re\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/11/recargar-coche-red-cambia-reglas-juego-2025111235-1764009454_1.jpg', '2025-12-13', 'https://www.motor.es/noticias/recargar-coche-red-cambia-reglas-juego-2025111235.html'),
(@admin_id, 'Alonso vuelve a sentir mariposas en el estómago: “Como en 2023. Estoy deseando que todos vean en qué hemos trabajado”', '
                            
                                
                            
                            Fernando Alonso, junto a su je\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/alonso-mariposas-estomago-deseando-vean-trabajado-2025111566-1765544135_1.jpg', '2025-12-13', 'https://www.motor.es/formula-1/alonso-mariposas-estomago-deseando-vean-trabajado-2025111566.html'),
(@admin_id, 'Así es como Mercedes promete reducir drásticamente sus emisiones de CO2: plásticos de segunda generación y nuevos conceptos', '
                            
                                
                            
                            Mercedes opta por el reciclaje\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/mercedes-tomorrow-xx-reduccion-emisiones-co2-2025111558-1765531816_3.jpg', '2025-12-13', 'https://www.motor.es/noticias/mercedes-tomorrow-xx-reduccion-emisiones-co2-2025111558.html'),
(@admin_id, 'Volkswagen quiere adelantar por la derecha a Tesla y los chinos con este coche autónomo ya operativo en Alemania', '
                            
                                
                            
                            Nuevas imágenes del VW Gen.Urb\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/volkswagen-coche-autonomo-gen-urban-2025111569-1765556956_4.jpg', '2025-12-13', 'https://www.motor.es/noticias/volkswagen-coche-autonomo-gen-urban-2025111569.html'),
(@admin_id, 'Europa toma una decisión que encarecerá los coches nuevos, y esta vez no tiene que ver con las normas emisiones', '
                            
                                
                            
                            Los coches nuevos volverán a s\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/europa-plastico-reciclado-coches-nuevos-2025111574-1765566743_1.jpg', '2025-12-12', 'https://www.motor.es/noticias/europa-plastico-reciclado-coches-nuevos-2025111574.html'),
(@admin_id, 'Alpine afronta un 2026 con grandes novedades, llega un nuevo deportivo para la era eléctrica', '
                            
                                
                            
                            Los nuevos modelos más importa\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/alpine-nuevos-modelos-2026-2025111570-1765562465_1.jpg', '2025-12-12', 'https://www.motor.es/noticias/alpine-nuevos-modelos-2026-2025111570.html'),
(@admin_id, 'El Volkswagen ID. Cross 2026 se destapa, el pequeño SUV eléctrico muestra sus armas para ir a por todas', '
                            
                                
                            
                            Fotos espía Volkswagen ID. Cro\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/volkswagen-id-cross-fotos-espia-2025111568-1765557161_2.jpg', '2025-12-12', 'https://www.motor.es/noticias/volkswagen-id-cross-2027-fotos-espia-destape-2025111568.html'),
(@admin_id, 'Llega un nuevo Acuerdo de la Concordia a la F1; la FIA recibirá mucho más dinero para gobernar la categoría', '
                            
                                
                            
                            La Fórmula 1 tiene nuevo acuer\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/acuerdo-concordia-f1-fia-recibira-dinero-gobernar-categoria-2025111572-1765559823_1.jpg', '2025-12-12', 'https://www.motor.es/formula-1/acuerdo-concordia-f1-fia-recibira-dinero-gobernar-categoria-2025111572.html'),
(@admin_id, 'La versión más interesante del DS 3 está de vuelta, estos son los precios de un acabado bien equipado y diseño deportivo', '
                            
                                
                            
                            Precios y equipamiento del nue\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/precio-ds-3-performance-line-2025111559-1765556073_1.jpg', '2025-12-12', 'https://www.motor.es/noticias/precio-ds-3-performance-line-2025111559.html'),
(@admin_id, 'Adiós a las maniobras de aparcamiento complicadas: las cuatro ruedas de este coche giran 90 grados y hacen movimientos imposibles', '
                            
                                
                            
                            Esta tecnología en un coche el\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/12/coche-electrico-chino-cuatro-ruedas-giran-90-grados-movimientos-imposibles-2025111565-1765544558_1.jpg', '2025-12-12', 'https://www.motor.es/noticias/coche-electrico-chino-cuatro-ruedas-giran-90-grados-movimientos-imposibles-2025111565.html'),
(@admin_id, 'Cómo el MG HS Hybrid+ cambió mi idea de lo que debe ser un híbrido familiar', '
                            
                                
                            
                            El nuevo MG HS Hybrid+ ya está\n\nFuente: Motor.es', 'https://static.motor.es/fotos-noticias/2025/11/prueba-mg-hs-hybrid-2025111322-1764330023_1.jpg', '2025-12-12', 'https://www.motor.es/pruebas-coches/prueba-mg-hs-hybrid-2025111322.html');
