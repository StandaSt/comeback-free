import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1584098906480 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "BRANCH", label: "Pobočky"},
            {name: "SHIFT", label: "Směny"}]);
        await addResources(queryRunner, [{
            name: "BRANCH_SEE_ALL",
            label: "Zobrazení všech poboček",
            description: "Zobrazení všech poboček",
            categoryName: "BRANCH",
            minimalCount: 0,
        }, {
            name: "BRANCH_CREATE",
            label: "Vytvoření pobočky",
            description: "Vytváření/editování/mazání pobočky.",
            categoryName: "BRANCH",
            minimalCount: 0,
        }, {
            name: "BRANCH_ASSIGN_PLANNER",
            label: "Přiřazení plánovaču k pobočce",
            description: "Přiřazování plánovačů (uživatelé co mohou plánovat směny) k pobočce.",
            categoryName: "BRANCH",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL", "BRANCH_SEE_ALL"],
        }, {
            name: "SHIFT_CAN_PLAN",
            label: "Plánování směn",
            description: "Plánování směn.",
            categoryName: "SHIFT",
            minimalCount: 0,
            requiredResource: ["USER_SEE_ALL"],
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["BRANCH_SEE_ALL", "BRANCH_CREATE", "BRANCH_ASSIGN_PLANNER",
            "SHIFT_CAN_PLAN"]);
        await removeResourceCategories(queryRunner, ["SHIFT", "BRANCH"]);
    }

}
