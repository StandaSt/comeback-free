import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1589184179884 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResources(queryRunner, [{
            name: "SHIFT_EDIT_TEMPLATE",
            categoryName: "SHIFT",
            label: "Upravování šablon",
            description: "Může upravovat/vytvářet šablony",
            requiredResource:["SHIFT_CAN_PLAN"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner,["SHIFT_EDIT_TEMPLATE"]);
    }

}
