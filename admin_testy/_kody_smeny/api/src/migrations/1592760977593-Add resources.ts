import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategory from "./scripts/addResourceCategory";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1592760977593 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategory(queryRunner, "BRANCHES", "Administrace - Pobočky");
        await addResources(queryRunner, [{
            name: "BRANCHES_SEE",
            label: "Zobrazení",
            categoryName: "BRANCHES",
            description: "Zobrazení poboček a jejich detailů"
        }, {
            name: "BRANCHES_ADD",
            label: "Přidávání",
            categoryName: "BRANCHES",
            description: "Přidávání poboček",
            requiredResource: ["BRANCHES_SEE"]
        }, {
            name: "BRANCHES_EDIT",
            label: "Upravování",
            categoryName: "BRANCHES",
            description: "Upravování informací o pobočkách",
            requiredResource: ["BRANCHES_SEE"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["BRANCHES_SEE", "BRANCHES_ADD", "BRANCHES_EDIT"]);
        await removeResourceCategories(queryRunner, ["BRANCHES"]);
    }

}
