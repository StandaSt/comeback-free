import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1587115297238 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResources(queryRunner, [{
            name: "SHIFT_ROLE_TYPE_ASSIGN_WORKER",
            categoryName: "SHIFT_ROLE_TYPE",
            label: "Přiřazení pracovníků",
            description: "Přiřazení zaměstanců k typu směny. Typy směn určují na jakých pozicích může zaměstatnec pracovat.",
            requiredResource: ["USER_SEE_ALL", "SHIFT_ROLE_TYPE_SEE_ALL"]
        }])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["SHIFT_ROLE_TYPE_ASSIGN_WORKER"])
    }

}
