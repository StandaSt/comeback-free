import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResourceCategories from "./scripts/removeResourceCategories";
import removeResources from "./scripts/removeResources";

export class AddResources1592905007726 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "SHIFT_ROLE_TYPES", label: "Administrace - Typy slotů"}]);
        await addResources(queryRunner, [{
            name: "SHIFT_ROLE_TYPES_SEE",
            label: "Zobrazení",
            categoryName: "SHIFT_ROLE_TYPES",
            description: "Zobrazení typů slotů a jejich detailů"
        }, {
            name: "SHIFT_ROLE_TYPES_ADD",
            label: "Přidávání",
            categoryName: "SHIFT_ROLE_TYPES",
            description: "Přidávání typů směn",
            requiredResource: ["SHIFT_ROLE_TYPES_SEE"]
        }, {
            name: "SHIFT_ROLE_TYPES_DELETE",
            label: "Odstraňování",
            categoryName: "SHIFT_ROLE_TYPES",
            description: "Odstraňování typů směn",
            requiredResource: ["SHIFT_ROLE_TYPES_SEE"]
        }, {
            name: "SHIFT_ROLE_TYPES_EDIT",
            label: "Upravování",
            categoryName: "SHIFT_ROLE_TYPES",
            description: "Upravování typů směn",
            requiredResource: ["SHIFT_ROLE_TYPES_SEE"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["SHIFT_ROLE_TYPES_SEE", "SHIFT_ROLE_TYPES_ADD", "SHIFT_ROLE_TYPES_DELETE", "SHIFT_ROLE_TYPES_EDIT"]);
        await removeResourceCategories(queryRunner, ["SHIFT_ROLE_TYPES"]);
    }

}
