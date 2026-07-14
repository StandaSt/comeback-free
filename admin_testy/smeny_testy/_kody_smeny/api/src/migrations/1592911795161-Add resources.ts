import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1592911795161 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{
            name: "PREFERRED_WEEKS",
            label: "Požadavky"
        }, {
            name: "NEWS",
            label: "Novinky"
        }, {
            name: "WORKING_WEEK",
            label: "Mé směny"
        }]);
        await addResources(queryRunner, [{
            name: "PREFERRED_WEEKS_SEE",
            label: "Zobrazení",
            categoryName: "PREFERRED_WEEKS",
            description: "Zobrazení požadavků. Umožní uživateli, aby byl přeřazen do směn"
        }, {
            name: "NEWS_SEE",
            label: "Zobrazení",
            categoryName: "NEWS",
            description: "Zobrazení novinek"
        }, {
            name: "WORKING_WEEK_SEE",
            label: "Zobrazení",
            categoryName: "WORKING_WEEK",
            description: "Zobrazení mých směn"
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["WORKING_WEEK_SEE", "NEWS_SEE", "PREFERRED_WEEKS_SEE"]);
        await removeResourceCategories(queryRunner, ["WORKING_WEEK", "NEWS", "PREFERRED_WEEKS"]);
    }

}
