import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1589804529973 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResources(queryRunner, [{
            name: "SHIFT_EDIT_PUBLISHED",
            categoryName: "SHIFT",
            label: "Editování publikovaných směn",
            description: "Může editovat již publikované směny bez nutnosti je vrátit do módu úpravy.",
            requiredResource: ["SHIFT_CAN_PLAN"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["SHIFT_EDIT_PUBLISHED"]);
    }

}
