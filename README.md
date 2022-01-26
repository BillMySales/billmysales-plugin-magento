Plugin BillMySales para Magento
===============================

Este módulo realiza el envío de los datos de un pedido a [BillMySales](https://billmysales.com)
para realizar el proceso de facturación del pedido.

El envío de los datos se realiza cada vez que el pedido cambia de estado y se
procesará según las reglas definidias en la pasarela de facturación en BillMySales.

El plugin fue probado con Magento 2.4.3-p1

Instalar a partir de este código fuente
---------------------------------------

1. Descargar el [código de este repositorio]().
2. Descomprimir el archivo.
3. Se debe copiar la carpeta `Sasco` en la siguiente ruta `magento/app/code/`
4. Ejecutar el comando `magento setup:upgrade && magento cache:flush`
5. Reiniciar el servidor `sudo reboot`

Licencia
--------

Este código está liberado bajo la licencia de software libre [AGPL](http://www.gnu.org/licenses/agpl-3.0.en.html).
Para detalles sobre cómo se puede utilizar, modificar y/o distribuir este plugin revisar los términos de la licencia.
