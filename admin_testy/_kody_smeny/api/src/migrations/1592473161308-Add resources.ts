import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import removeResourceCategories from "./scripts/removeResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1592473161308 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "USERS", label: "Administrace - Uživatelé"}])
        await addResources(queryRunner, [{
            name: "USERS_SEE",
            label: "Zobrazení",
            categoryName: "USERS",
            description: "Zobrazení uživatelů a jejich detailů"
        }, {
            name: "USERS_ADD",
            label: "Přidávání",
            categoryName: "USERS",
            description: "Přidávání/registrace uživatelů",
            requiredResource: ["USERS_SEE"]
        }, {
            name: "USERS_EDIT",
            label: "Upravování",
            categoryName: "USERS",
            description: "Upravování informací o uživatelích",
            requiredResource: ["USERS_SEE"]
        }, {
            name: "USERS_NOTIFY_AFTER_REGISTRATION",
            label: "Upozorňování po registraci",
            categoryName: "USERS",
            description: "Upozorňování formou emailu pokud se uživatel zaregistruje do systému sám",
            requiredResource: ["USERS_SEE"]
        }])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["USERS_SEE", "USERS_ADD", "USERS_EDIT", "USERS_NOTIFY_AFTER_REGISTRATION"])

        await removeResourceCategories(queryRunner, ["USERS"]);
    }

}
