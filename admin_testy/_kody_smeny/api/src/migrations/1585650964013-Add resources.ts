import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1585650964013 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["SHIFT_CAN_PLAN"]);
        await addResources(queryRunner, [{
            name: "BRANCH_ACTIVATE",
            label: "Aktivování poboček",
            description: "Aktivování/deaktivování poboček.",
            categoryName: "BRANCH",
            minimalCount: 0,
        }, {
            name: "BRANCH_EDIT",
            label: "Editace poboček",
            description: "Editace poboček.",
            categoryName: "BRANCH",
            minimalCount: 0,
            requiredResource: ["BRANCH_SEE_ALL"]
        }, {
            name: "BRANCH_SEE_SHIFT_WEEKS",
            label: "Zobrazení pracovních týdnů",
            description: "Zobrazí všechny pracovní týdny poboček",
            categoryName: "BRANCH",
            minimalCount: 0,
        }, {
            name: "USER_EDIT",
            label: "Editace uživatelů",
            description: "Editace uživatelů.",
            categoryName: "USER",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL"]
        }, {
            name: "SHIFT_CAN_PLAN",
            label: "Plánování směn",
            description: "Plánování směn.",
            categoryName: "SHIFT",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL", "BRANCH_SEE_SHIFT_WEEKS"],
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["BRANCH_ACTIVATE", "BRANCH_EDIT", "BRANCH_SEE_SHIFT_WEEKS", "USER_EDIT"]);
        await addResources(queryRunner, [{
            name: "SHIFT_CAN_PLAN",
            label: "Plánování směn",
            description: "Plánování směn.",
            categoryName: "SHIFT",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL"],
        }]);
    }

}
