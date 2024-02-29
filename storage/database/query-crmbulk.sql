create table crm_bulk (
	id serial primary key,
	regnro integer,
	idenvio integer,
	FOREIGN KEY(regnro) 
		REFERENCES opeges(regnro)
)

create table historial_idenvio_crmbulk(
	id serial primary key,
	regnro integer,
	idenviotigo integer,
	FOREIGN KEY(regnro) 
		REFERENCES opeges(regnro)
)
