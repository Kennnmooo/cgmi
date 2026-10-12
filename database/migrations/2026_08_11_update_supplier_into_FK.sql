-- update the supplier column in the checker_records into FK 

ALTER TABLE checker_records
MODIFY COLUMN supplier INT(11);

ALTER TABLE checker_records
ADD CONSTRAINT fk_checkerRecords_to_supplier
FOREIGN KEY (supplier)
REFERENCES supplier(supplierId);