import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1603274033679 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{label: "Logování", name: "ACTION_HISTORY"}]);
        await addResources(queryRunner, [{
            name: "ACTION_HISTORY_SEE",
            categoryName: "ACTION_HISTORY",
            label: "Zobrazení",
            description: "Zobrazení logování"
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["ACTION_HISTORY_SEE"]);
        await removeResourceCategories(queryRunner, ["ACTION_HISTORY"]);
    }

}
