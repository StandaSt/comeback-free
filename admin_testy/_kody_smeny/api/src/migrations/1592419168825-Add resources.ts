import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import addResourceCategories from "./scripts/addResourceCategories";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1592419168825 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "ROLES", label: "Administrace - Role"}]);
        await addResources(queryRunner, [{
            name: "ROLES_SEE",
            label: "Zobrazení",
            categoryName: "ROLES",
            description: "Zobrazení rolí a pravomocí"
        }, {
            name: "ROLES_EDIT_ROLES",
            label: "Upravování rolí",
            categoryName: "ROLES",
            description: "Přidávání, upravování a odstraňování rolí",
            requiredResource: ["ROLES_SEE"]
        }, {
            name: "ROLES_EDIT_RESOURCES",
            label: "Upravování pravomocí",
            categoryName: "ROLES",
            description: "Úprava/přiřazování pravomocí",
            requiredResource: ["ROLES_SEE"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["ROLES_SEE", "ROLES_EDIT_ROLES", "ROLES_EDIT_RESOURCES"]);
        await removeResourceCategories(queryRunner, ["ROLES"]);
    }

}
