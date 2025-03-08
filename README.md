Cliente API para OpenEMR. Busca paciente por Apellido o por Documento (pubpid).
Debe ser un usuario con permiso de leer y agregar datos del paciente.
![Login](login.png)

Click en Authorize.
![Authorize](authorize.png)

Cliente, elegis Apellido o Documento.
![Inicio-Cliente](inicio-cliente.png)

La busqueda no es case sensitive y se puede ingresar parte del texto.
![Busqueda](busqueda.png)

Si el Apellido o Documento no existe, esta la posibilidad de darlo de alta. 
![Alta](alta.png)

Para obtener JSON Web Key Sets (jwks.json) ir al sitio https://mkjwk.org/,
Generar set con estos parametros:
• Key Size: 2048
• Key Use: Signature
• Algorithm: RS384:RSA
• Key ID: SHA-256
• Show X.509 : Yes
Elegir Public and Private Keypair Set, agregar ese contenido al archivo jwks.json.
Luego registrar un nuevo similar a la imagen
Ejemplo registro cliente:
![Registro](image.png)
La mayoria del código fue realizado con Grok AI.
