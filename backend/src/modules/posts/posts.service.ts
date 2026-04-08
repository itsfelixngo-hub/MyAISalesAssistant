import { Injectable } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Model, Types } from 'mongoose';
import { Post } from './post.schema';
import { I18nService } from 'nestjs-i18n';
import { CreatePostDto } from './dtos/create.post.dto';
import { ExErrorException, ExRedirectException } from 'src/common/error.filter';
import { FilterPostsDto, FilterSiteMapDto } from './dtos/filter.post.dto';
import { parseListString, parseNumberList } from 'src/utils/parse.util';

@Injectable()
export class PostsService {
  constructor(
    @InjectModel(Post.name) private readonly postModel: Model<Post>,
    private readonly i18n: I18nService
  ) { }

  isStrictObjectId(s: string) {
    return Types.ObjectId.isValid(s);
  }
  
  private async assertKeyNotUsedInSlugOrSlugOld(
    lang: string,
    key?: string,
    excludeId?: string,
  ) {
    const k = (key ?? '').trim();
    if (!k) return;

    const q: any = { lang, $or: [{ slug: k }, { slugOld: k }] };
    if (excludeId) {
      if(this.isStrictObjectId(excludeId)) q._id = { $ne: excludeId };
      else {
        q.slug = { $ne: excludeId };
      }
    }

    const conflict = await this.postModel.exists(q);
    if (conflict) {
      const msg = this.i18n.translate('errors.CREATE_RECORD_FAIL', { lang });
      throw new ExErrorException('SLUG_CONFLICT', 409, msg);
    }
  }


  async create(createPostDto: CreatePostDto, userId: string, lang?: string): Promise<Post> {
    const { slug, slugOld } = createPostDto;

    await this.assertKeyNotUsedInSlugOrSlugOld(lang ?? 'en', slug);
    if(slugOld) await this.assertKeyNotUsedInSlugOrSlugOld(lang ?? 'en', slugOld);
    
    try {
      const post = new this.postModel({
        ...createPostDto,
        author: userId,
      });
      return await post.save();

    } catch (error) {
      const msg = this.i18n.translate('errors.CREATE_RECORD_FAIL', { lang });

      throw new ExErrorException(
        'CREATE_RECORD_FAIL',
        500,
        msg,
        error.message
      );
    }
  }

  async findAll(query: FilterPostsDto, lang?: string): Promise<{
    data: Post[];
    meta: {
      page: number;
      limit: number;
      total: number;
      pageCount: number;
    };
  } | null> {

    try {
      const {
        page = 1,
        limit = 10,
        lang,
        type,
        status,
        exclude,
        include,
        reviews,
        views,
        pinTop,
        category,
        tag,
        keyword,
        startDate, endDate, sort = 'desc'
      } = query;

      const filter: Record<string, any> = {};
      const orFilters: Record<string, any>[] = [];
      const sortObj: any = {};

      const _type = parseListString(query.type);
      const _status = parseListString(query.status);
      const cats = parseNumberList(query.category);
      const tags = parseNumberList(query.tag);

      if (lang) filter.lang = lang;

      if (_type?.length) {
        if (_type.length > 1) {
          orFilters.push(..._type.map((t) => ({ type: t })));
        } else {
          filter.type = { $in: _type };
        }
      }

      if (_status?.length) {
        if (_status.length > 1) {
          orFilters.push(..._status.map((s) => ({ status: s })));
        } else {
          filter.status = { $in: _status };
        }
      }

      if (orFilters.length) {
        filter.$or = orFilters;
      }

      if (cats.length) filter.categories = { $in: cats };
      if (tags.length) filter.tags = { $in: tags };

      if (keyword) {
        filter.$or = [
          { title: { $regex: keyword, $options: 'i' } },
          { content: { $regex: keyword, $options: 'i' } },
        ];
      }

      if (startDate || endDate) {
        filter.createdAt = {};

        if (startDate) {
          const start = new Date(startDate);
          start.setHours(0, 0, 0, 0);
          filter.createdAt.$gte = start;
        }

        if (endDate) {
          const end = new Date(endDate);
          end.setHours(23, 59, 59, 999);
          filter.createdAt.$lte = end;
        }
      }
      const _excludeList = parseListString(query.exclude);
      const _includeList = parseListString(query.include);
      if (_excludeList?.length) {
        filter._id = { $nin: _excludeList };
      }else if (_includeList?.length) {
        filter._id = { $in: _includeList };
      }

      if (query.pinTop === true) {
        // Ưu tiên các bản ghi có pintop = true lên đầu
        sortObj.pinTop = -1;
      } else if (query.reviews === true) {
        // Sắp xếp theo số lượng reviews nếu có yêu cầu 
        sortObj.reviews = -1;
      } else if(query.views) {
        // Sắp xếp theo số lượng views nếu có yêu cầu
        sortObj.views = -1;
      } else {
        sortObj.createdAt = query.sort === 'asc' ? 1 : -1;
      }
      
      //console.log('filter:', JSON.stringify(filter, null, 2));

      const total = await this.postModel.countDocuments(filter);
      const data = await this.postModel
        .find(filter)
        .populate('author', ['email', 'niceName', 'displayName', 'avatar'])
        .skip((page - 1) * limit)
        .limit(limit)
        .sort(sortObj)
        .exec();

      return {
        data,
        meta: {
          page,
          limit,
          total,
          pageCount: Math.ceil(total / limit),
        },
      };
    } catch (error) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        500,
        msg,
        error.message
      );
    }
  }

  async siteMap(query: FilterSiteMapDto) {
      const _lang = parseListString(query.lang);
      const _type = parseListString(query.type);
      const _status = parseListString(query.status);
      const _excludeList = parseListString(query.exclude);
      const _includeList = parseListString(query.include);

      const filter: Record<string, any> = {};
      const orFilters: Record<string, any>[] = [];

      if(_lang?.length) filter.lang = _lang;

      if (_type?.length) {
        if (_type.length > 1) {
          orFilters.push(..._type.map((t) => ({ type: t })));
        } else {
          filter.type = { $in: _type };
        }
      }

      if (_status?.length) {
        if (_status.length > 1) {
          orFilters.push(..._status.map((s) => ({ status: s })));
        } else {
          filter.status = { $in: _status };
        }
      }

      if (orFilters.length) {
        filter.$or = orFilters;
      }

      if (_excludeList?.length) {
        filter._id = { $nin: _excludeList };
      } else if (_includeList?.length) {
        filter._id = { $in: _includeList };
      }
      console.log(filter);
    try {
      return await this.postModel
        .find(filter)
        .select(['title', 'slug', 'excerpt', 'lang']).lean();
      
    } catch (error) {
       const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND');
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        500,
        msg,
        error.message
      );
    }
  }



  async findOneById(key: string, lang?: string): Promise<Post | null> {
    const filter: any[] = [{ slug: key}];
    if(this.isStrictObjectId(key)) filter.unshift({ _id: key });

    // 1) tìm theo _id/slug và tăng views
    let result = await this.postModel
      .findOneAndUpdate(
        { lang, $or: filter },
        { $inc: { views: 1 } },
        { new: true },
      )
      .populate('author', ['email', 'niceName', 'displayName', 'avatar'])
      .exec();

    if (result) return result;

    // 2) fallback slugOld
    const filterOld = [{ slugOld: key }, ...filter];

    result = await this.postModel
      .findOne({ lang, $or: filterOld })
      .populate('author', ['email', 'niceName', 'displayName', 'avatar'])
      .exec();
      
    if (!result) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException('FETCH_RECORD_NOT_FOUND', 404, msg);
    }

    // 3) redirect
    const msg = this.i18n.translate('errors.FETCH_RECORD_REDIRECT', { lang });
    throw new ExRedirectException(`/posts/${result.slug}`, result.slug, 'FETCH_RECORD_REDIRECT', 301, msg, msg);
  }


  async update(id: string, updateDto: Partial<CreatePostDto>, lang?: string): Promise<Post | null> {
    const { slug, slugOld } = updateDto;

    await this.assertKeyNotUsedInSlugOrSlugOld(lang ?? 'en', slug, id);
    if(slugOld) await this.assertKeyNotUsedInSlugOrSlugOld(lang ?? 'en', slugOld, id);

    const fetch = await this.postModel.findOne({ _id: id, lang }).exec();

    if (!fetch) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        404,
        msg
      );
    }

    // 2) Nếu có đổi slug → kiểm tra trùng trong cùng lang (loại trừ chính nó)
    if (typeof updateDto.slug === 'string') {
      const newSlug = updateDto.slug.trim();

      if (!newSlug) {
        // Không cho set slug rỗng
        delete (updateDto as any).slug;
      } else if (newSlug !== fetch.slug) {
        const checkLang = lang ?? fetch.lang;
        const dup = await this.postModel.exists({
          _id: { $ne: fetch._id },
          slug: newSlug,
          lang: checkLang,
        });
        if (dup) {
          const msg = this.i18n.translate('errors.CREATE_RECORD_FAIL', { lang });
          throw new ExErrorException('SLUG_POST_EXIST', 403, msg);
        }
        (updateDto as any).slug = newSlug; // chuẩn hoá lại
      } else {
        // slug không đổi → bỏ qua để tránh đụng unique index không cần thiết
        delete (updateDto as any).slug;
      }
    }

    try {
      const result = await this.postModel.findOneAndUpdate({ _id: id, lang }, updateDto, {
        new: true,
      }).exec();

      return result;
    } catch (error) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        500,
        msg,
        error.message
      );
    }
  }

  async pinTop(id: string, lang?: string): Promise<Post | null> {
    const fetch = await this.postModel.findOne({ _id: id, lang }).exec();
    if (!fetch) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        404,
        msg
      );
    }

    try {
      const result = await this.postModel.findOneAndUpdate(
        { _id: id, lang },
        { pinTop: true},
        { new: true }).exec();

      return result;
    } catch (error) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        500,
        msg,
        error.message
      );
    }
  }

  async delete(id: string, lang?: string): Promise<Post | null> {
    const fetch = await this.postModel.findOne({ _id: id, lang }).exec();
    if (!fetch) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        404,
        msg,
      );
    }
    try {
      await this.postModel.deleteOne({ _id: id, lang }).exec();
      return fetch;
    } catch (error) {
      const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
      throw new ExErrorException(
        'FETCH_RECORD_NOT_FOUND',
        404,
        msg
      );
    }
  }

  async slugExist(slug:string, lang?: string) {
    const fetch = await this.postModel.exists({ slug, lang }).exec();
    // console.log(fetch);
    if(fetch) return true;
    return false;
  }
}
