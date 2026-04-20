import {QueryRunner} from "typeorm";
import ResourceCategory from "resourceCategory/resourceCategory.entity";

const removeResourceCategory = async (queryRunner: QueryRunner, resourceCategoryName: string) => {
    const resourceCategoryRepository = queryRunner.manager.getRepository(ResourceCategory);
    const category = await resourceCategoryRepository.findOne({name: resourceCategoryName});
    await resourceCategoryRepository.remove([category])
};

export default removeResourceCategory