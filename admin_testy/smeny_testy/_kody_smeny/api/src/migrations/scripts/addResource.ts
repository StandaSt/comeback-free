import {QueryRunner} from 'typeorm';
import ResourceCategory from 'resourceCategory/resourceCategory.entity';
import Resource from 'resource/resource.entity';

const addResource = async (queryRunner: QueryRunner, name: string, label: string, description: string, categoryName: string, minimalCount: number, requiredResources: string[]) => {
    const resourceCategoryRepository = await queryRunner.manager.getRepository(ResourceCategory);

    const resourceRepository = await queryRunner.manager.getRepository(Resource);

    const category = await resourceCategoryRepository
        .findOne({name: categoryName});
    const resource = new Resource();
    resource.name = name;
    resource.label = label;
    resource.category = Promise.resolve(category);
    resource.description = description;
    resource.minimalCount = minimalCount;
    for (const requiredResource of requiredResources) {
        const r = await resourceRepository.findOne({name: requiredResource});
        if (r) {
            (await resource.requires).push(r);
        }
    }
    await queryRunner.manager.getRepository(Resource).save(resource);
};

export default addResource;
