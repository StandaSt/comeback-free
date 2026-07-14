import {MigrationInterface, QueryRunner} from "typeorm";
import addResourceCategories from "./scripts/addResourceCategories";
import addResources from "./scripts/addResources";
import removeResources from "./scripts/removeResources";
import removeResourceCategories from "./scripts/removeResourceCategories";

export class AddResources1601830618001 implements MigrationInterface {

    public async up(queryRunner: QueryRunner): Promise<any> {
        await addResourceCategories(queryRunner, [{
            name: "WEEK_SUMMARY",
            label: "Naplánované směny"
        }, {
            name: "ENTERED_PREFERRED_WEEKS", label: "Zadané požadavky"
        }
        ]);
        await addResources(queryRunner, [{
            name: "WEEK_SUMMARY_SEE",
            label: "Zobrazení",
            categoryName: "WEEK_SUMMARY",
            description: "Zobrazení naplánovaných směn",
        }, {
            name: "ENTERED_PREFERRED_WEEKS_SEE",
            label: "Zobrazení",
            categoryName: "ENTERED_PREFERRED_WEEKS",
            description: "Zobrazení zadaných požadavků",
        }]);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await removeResources(queryRunner, ["WEEK_SUMMARY_SEE", "ENTERED_PREFERRED_WEEKS_SEE"]);
        await removeResourceCategories(queryRunner, ["WEEK_SUMMARY", "ENTERED_PREFERRED_WEEKS"]);
    }

}
