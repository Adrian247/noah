**Actualización de contexto**



He identificado una actualización importante para la lógica de negocio. Las rutinas no atienden siempre a  un mantenimiento, también a servicios de manufactura, proveedor de cliente (compra de insumos de inventario) por lo tanto se necesita resolver:

- Catalogar los tipos de rutina por servicio, puedes elegir el nombre más adecuado para esta situación tanto para su categorización así como el tipo de acción, las acciones a catalogar son:
  - Mantenimiento
  - Manufactura
  - Proveedor
- El algoritmo de predicción deberá actualizarse o desacoplarse para lograr predicir por ejemplo:
  - Un equipo requerirá mantenimiento y de que tipo analizando antecedente de rutinas de mantenimiento.
  - Un cliente requerirá un servicio de manufactura y de que tipo.
    - Esto pasa por lo siguiente, imagina que el cliente Velardeña de Mein Company solicita la elaboración de estructuras para uso en obra civil, entonces esto no representa una rutina de mantenimiento si no de manufactura, donde se gasta en mano de obra y bajas de inventario por los insumos utilizados.
  - Un cliente requerirá un servicio de compra o proveedor de alguno de los artículos de mi inventario
    - Esto pasa por lo siguiente imagina que yo como mein company un cliente por ejemplo mina Velardeña solicita cuchillas, entonces yo como Mein Company consigo esas cuchillas con uno de mis proveedores para darlas de alta en mi inventario y a su vez venderlas a Velardeña por eso es como brindar un servicio de proveedor a Velardeña
- Otro punto importante identificado es resolver es que dado la implementación anterior la rutina podría o no apuntar a un activo del cliente.
- En la predicción actual cuando no tiene rutinas alertas de que no se analizan bitácoras excel, esto es un legacy deprecado, quita esa mención de la alerta
- El versionamiento del algoritmo tal vez no es el adecuado ya que desde la web lo único realizar siempre será un entrenamiento y nada más, para actualizar de tipo patch se realizarán desde este punto a solicitud de desarrollado, es decir ofuscado de la web, resuelve este tema también.
- Los catologos de equipos Epiroc, Sandvik, equipos planta, etc agregalos para las dos cuentas actuales Mein Company y Dom-G.
  - Aprovechando sobre este punto de actualización de cuentas actualiza la contraseña estándar para todas la cuentas, incluyendo administradores de sistema, administradores clientes, usuarios y clientes, estamos en fase de pruebas de momento no hay problema, la contraseña debe ser: pyro.2026$
- Implementa las siguientes configuraciones base
  - Mein Company
    - Actualiza el cliente demo como Mina Velardeña
    - Agrega otro cliente llamado Presidencia Municipal Sombrerete
  - Dom-g
    - Actualiza el cliente demo como Grupo México
  - Para Mein Company y Dom-g agrega un cliente nuevo como Interno, esto con la finalidad de registrar los trabajos internos de manufactura, mantenimiento, etc.
- Insisto que los nombres utilizados como Mantenimiento, Manufactura, Proveedor, etc que utilice para el planteamiento no significan la norma, si identificas ambiguedad o que otro nombre es más adecuado no tengo problema, implementalo.
- Considera los cambios con base a todo lo anterior en la aplicación móvil.

