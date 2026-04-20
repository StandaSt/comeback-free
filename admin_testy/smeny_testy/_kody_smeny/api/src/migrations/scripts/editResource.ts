import {QueryRunner} from 'typeorm';
import ResourceCategory from 'resourceCategory/resourceCategory.entity';
import Resource from 'resource/resource.entity';

const editResource = async (queryRunner: QueryRunner, name: string, label?: string, description?: string, categoryName?: string, minimalCount?: number, requiredResources?: string[]) => {
    const resourceCategoryRepository = await queryRunner.manager.getRepository(ResourceCategory);

    const resourceRepository = await queryRunner.manager.getRepository(Resource);

    const resource = await resourceRepository.findOne({name})

    if (categoryName) {
        const category = await resourceCategoryRepository
            .findOne({name: categoryName});
        resource.category = Promise.resolve(category);
    }
    if (label)
        resource.label = label;
    if (description)
        resource.description = description;
    if (minimalCount)
        resource.minimalCount = minimalCount;
    if (requiredResources)
        for (const requiredResource of requiredResources) {
            const r = await resourceRepository.findOne({name: requiredResource});
            if (r) {
                (await resource.requires).push(r);
            }
        }
    await queryRunner.manager.getRepository(Resource).save(resource);
};

export default editResource;
