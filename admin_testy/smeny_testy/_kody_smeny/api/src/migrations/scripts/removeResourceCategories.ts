import {QueryRunner} from "typeorm";
import removeResourceCategory from "./removeResourceCategory";

const removeResourceCategories = async (queryRunner: QueryRunner, resourceCategoryNames: string[]) => {
    for (const category of resourceCategoryNames) {
        await removeResourceCategory(queryRunner, category);
    }
};

export default removeResourceCategories;