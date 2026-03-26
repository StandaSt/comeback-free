import {MigrationInterface, QueryRunner} from "typeorm";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";

export class AddResources1603985158388 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResources(queryRunner, [{
            name: "WEEK_PLANNING_CLEAR",
            categoryName: "WEEK_PLANNING",
            label: "Vyprázdění",
            description: "Vyprazdňování celého týdne",
            requiredResource: ["WEEK_PLANNING_PLAN"]
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["WEEK_PLANNING_CLEAR"]);
    }

}
