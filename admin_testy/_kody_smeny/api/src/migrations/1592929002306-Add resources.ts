import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1592929002306 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{name: "WEEK_PLANNING", label: "Plánování směn"}]);
        await addResources(queryRunner, [{
            name: "WEEK_PLANNING_SEE",
            label: "Zobrazení",
            categoryName: "WEEK_PLANNING",
            description: "Zobrazení plánovaných směn"
        }, {
            name: "WEEK_PLANNING_PLAN",
            label: "Plánování",
            categoryName: "WEEK_PLANNING",
            description: "Plánování směn",
            requiredResource: ["WEEK_PLANNING_SEE"]
        }, {
            name: "WEEK_PLANNING_PUBLISH",
            label: "Publikování",
            categoryName: "WEEK_PLANNING",
            description: "Publikování naplánovaných směn",
            requiredResource: ["WEEK_PLANNING_SEE"]
        }, {
            name: "WEEK_PLANNING_PLAN_PUBLISHED",
            label: "Plánování publikovaných",
            categoryName: "WEEK_PLANNING",
            description: "Plánování již publikovaných směn",
            requiredResource: ["WEEK_PLANNING_SEE", "WEEK_PLANNING_PLAN"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["WEEK_PLANNING_SEE", "WEEK_PLANNING_PLAN", "WEEK_PLANNING_PUBLISH", "WEEK_PLANNING_PLAN_PUBLISHED"]);
        await removeResourceCategories(queryRunner, ["WEEK_PLANNING"]);
    }

}
