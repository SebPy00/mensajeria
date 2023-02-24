--EJECUTAR Y PROBAR // usaremos base de test?
create table factura_electronica (
    id serial Primary Key,
    nro_factura character(30),
    nota_credito character(30) default null,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);