import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1584091847755 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "ROLE", label: "Role"}, {name: "USER", label: "Uživatelé"}]);
        await addResources(queryRunner, [{
            name: "ROLE_EDIT",
            label: "Editace rolí",
            description: "Editace, vytváření a mazání rolí.",
            categoryName: "ROLE",
            minimalCount: 1,
        }, {
            name: "USER_SEE_ALL",
            label: "Zobrazení všech uživatelů",
            description: "Zobrazení všech uživatelů.",
            categoryName: "USER",
            minimalCount: 1,
        }, {
            name: "USER_ADD",
            label: "Přidávání uživatelů",
            description: "Přidávání uživatelů.",
            categoryName: "USER",
            minimalCount: 0,
        }, {
            name: "USER_ACTIVATE",
            label: "Aktivace uživatelů",
            description: "Aktivace a deaktivace uživatelů.",
            categoryName: "USER",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL"]
        }, {
            name: "USER_GENERATE_PASSWORD",
            label: "Generování hesla uživatelů",
            description: "Generování nového heslo pro uživatele.",
            categoryName: "USER",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL"]
        }, {
            name: "ROLE_ASSIGN_USER",
            label:"Přiřazování rolí uživatelům",
            description: "Přiřazování rolí uživatelům.",
            categoryName: "ROLE",
            minimalCount: 1,
            requiredResource: ["USER_SEE_ALL"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["ROLE_ASSIGN_USER", "USER_GENERATE_PASSWORD", "USER_ACTIVATE",
            "USER_ADD", "USER_SEE_ALL", "ROLE_EDIT"]);
        await removeResourceCategories(queryRunner, ["ROLE", "USER"]);
    }

}
