import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import addResourceCategories from "./scripts/addResourceCategories";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";
import removeResource from "./scripts/removeResource";

export class AddResources1585655954611 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await removeResource(queryRunner, "SHIFT_CAN_PLAN");
        await addResourceCategories(queryRunner, [{name: "SHIFT_ROLE_TYPE", label: "Typy směn"}]);
        await addResources(queryRunner, [
            {
                name: "SHIFT_ROLE_TYPE_SEE_ALL",
                label: "Zobrazení všech typů",
                description: "Zobrazení všech typů.",
                categoryName: "SHIFT_ROLE_TYPE",
                minimalCount: 0,
            }, {
                name: "SHIFT_ROLE_TYPE_CREATE",
                label: "Vytváření typů směn",
                description: "Vytváření typů směn.",
                categoryName: "SHIFT_ROLE_TYPE",
                minimalCount: 0,
            }, {
                name: "SHIFT_ROLE_TYPE_DEACTIVATE",
                label: "Odstranění typů směn",
                description: "Odstranění typů směn.",
                categoryName: "SHIFT_ROLE_TYPE",
                minimalCount: 0,
                requiredResource: ["SHIFT_ROLE_TYPE_SEE_ALL"]
            }, {
                name: "SHIFT_ROLE_TYPE_EDIT",
                label: "Editace typů směn",
                description: "Editace typů směn.",
                categoryName: "SHIFT_ROLE_TYPE",
                minimalCount: 0,
                requiredResource: ["SHIFT_ROLE_TYPE_SEE_ALL"]
            }, {
                name: "SHIFT_CAN_PLAN",
                label: "Plánování směn",
                description: "Plánování směn.",
                categoryName: "SHIFT",
                minimalCount: 0,
                requiredResource: ["USER_SEE_ALL", "BRANCH_SEE_SHIFT_WEEKS", "SHIFT_ROLE_TYPE_SEE_ALL"],
            }])
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["SHIFT_ROLE_TYPE_SEE_ALL", "SHIFT_ROLE_TYPE_CREATE",
            "SHIFT_ROLE_TYPE_DEACTIVATE", "SHIFT_ROLE_TYPE_EDIT"]);
        await removeResourceCategories(queryRunner, ["SHIFT_ROLE_TYPE"]);
        await addResources(queryRunner, [{
            name: "SHIFT_CAN_PLAN",
            label: "Plánování směn",
            description: "Plánování směn.",
            categoryName: "SHIFT",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL", "BRANCH_SEE_SHIFT_WEEKS"],
        }])
    }
}
