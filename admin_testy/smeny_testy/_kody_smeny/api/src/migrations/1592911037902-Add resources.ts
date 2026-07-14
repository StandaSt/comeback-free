import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1592911037902 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{
            name: "GLOBAL_SETTINGS",
            label: "Administrace - Globální nastavení"
        }]);
        await addResources(queryRunner, [{
            name: "GLOBAL_SETTINGS_SEE",
            label: "Zobrazení",
            categoryName: "GLOBAL_SETTINGS",
            description: "Zobrazení globálního nastavení"
        }, {
            name: "GLOBAL_SETTINGS_EDIT",
            label: "Upravování",
            categoryName: "GLOBAL_SETTINGS",
            description: "Upravování globálního nastavení",
            requiredResource: ["GLOBAL_SETTINGS_SEE"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["GLOBAL_SETTINGS_SEE", "GLOBAL_SETTINGS_EDIT"]);
        await removeResourceCategories(queryRunner, ["GLOBAL_SETTINGS"]);
    }

}
